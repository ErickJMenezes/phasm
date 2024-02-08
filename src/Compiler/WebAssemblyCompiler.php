<?php

namespace ErickJMenezes\Phasm\Compiler;

use ErickJMenezes\Phasm\Compiler\Scopes\FileScope;
use ErickJMenezes\Phasm\Compiler\Scopes\NamespaceScope;
use ErickJMenezes\Phasm\Compiler\Scopes\VariableScope;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RuntimeException;

class WebAssemblyCompiler
{
    private static array $libPaths = [
        __DIR__.'/../../libphasm/core.php',
    ];

    private static array $macros = [];

    private string $startFunc = '';

    private array $imports = [];

    public function __construct() {}

    public function compileRoot(string $code): string
    {
        $body = [];
        foreach (self::$libPaths as $stdLibs) {
            $body[] = FileScope::create(
                $stdLibs,
                fn () => $this->compileCode(file_get_contents($stdLibs))
            );
        }
        $body[] = FileScope::create('$root', fn () => $this->compileCode($code));
        $fullBody = implode('', $body);
        $imports = implode(' ', $this->imports);
        return "(module \n$imports \n$fullBody \n{$this->startFunc})";
    }

    public function compileCode(string $code): string
    {
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        return $this->compileNodes($parser->parse($code));
    }

    /**
     * @param array<Node> $nodes
     */
    public function compileNodes(array $nodes): string
    {
        $compiledNodes = [];
        foreach ($nodes as $node) {
            $compiledNode = $this->compileNode($node);
            if (empty($compiledNode)) {
                continue;
            }
            $compiledNodes[] = $compiledNode;
        }
        return implode(PHP_EOL, $compiledNodes);
    }

    public function compileNode(Node $node): string
    {
        if ($node instanceof Node\Expr\Assign) {
            return $this->compileAssignmentStatement($node);
        } elseif ($node instanceof Node\Identifier) {
            return $this->compileIdentifier($node);
        } elseif (
            $node instanceof Node\Scalar\LNumber ||
            $node instanceof Node\Scalar\DNumber
        ) {
            return "({$this->getExpressionType($node)}.const {$node->value})";
        } elseif ($node instanceof Node\Stmt\Expression) {
            return $this->compileNode($node->expr);
        } elseif ($node instanceof Node\Expr\Variable) {
            return $this->compileVariableExpression($node);
        } elseif ($node instanceof Node\Expr\BinaryOp) {
            return $this->compileArithmeticExpression($node);
        } elseif ($node instanceof Node\Stmt\Function_) {
            return $this->compileFunctionDeclaration($node);
        } elseif ($node instanceof Node\Expr\FuncCall) {
            return $this->compileFunctionCall($node);
        } elseif ($node instanceof Node\Stmt\Return_) {
            return $this->compileReturnStatement($node);
        } elseif ($node instanceof Node\Stmt\Class_) {
            return '';
        } elseif ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $item) {
                FileScope::current()->addUse($item->name->toString());
            }
            return '';
        } elseif ($node instanceof Node\Expr\Include_) {
            return $this->compileCode(file_get_contents($node->expr->value));
        } elseif ($node instanceof Node\Stmt\Namespace_) {
            return FileScope::current()
                ->namespace(
                    $node->name->toString(),
                    fn (NamespaceScope $namespaceScope) => $namespaceScope
                        ->variableScope()
                        ->create($namespaceScope->name, fn () => $this->compileNodes($node->stmts))
                );
        }
        var_dump($node);
        exit(-1);
    }

    private function compileAssignmentStatement(Node\Expr\Assign $assign): string
    {
        $name = "\${$assign->var->name}";
        $globalVisibility = $this->getVariableScope()
            ->isGlobal() ? 'global' : 'local';
        $type = $this->getExpressionType($assign->expr);

        $declaration = "($globalVisibility $name $type)";
        $expr = $this->compileNode($assign->expr);
        $assignment = "($globalVisibility.set $name $expr)";

        $this->getVariableScope()
            ->declare($name, $type, $declaration);

        return $assignment;
    }

    private function getExpressionType(Node $node): string
    {
        if ($node instanceof Node\Scalar\LNumber) {
            return 'i64';
        } elseif ($node instanceof Node\Scalar\DNumber) {
            return 'f64';
        } elseif ($node instanceof Node\Expr\Variable) {
            return $this->getVariableScope()
                ->getType("\${$node->name}");
        } elseif ($node instanceof Node\Expr\BinaryOp) {
            return $this->getExpressionType($node->left);
        } elseif ($node instanceof Node\Expr\FuncCall) {
            return $this->getVariableScope()
                ->getTypeFromNamespace(
                    FileScope::current()->namespaceOf($node->name->toString()),
                    FileScope::current()->qualify($node->name->toString())
                );
        } else {
            throw new \RuntimeException("could not identify node type. ".$node::class);
        }
    }

    private function compileIdentifier(Node\Identifier $node): string
    {
        return $node->name;
    }

    private function compileVariableExpression(Node\Expr\Variable $node): string
    {
        $globalVisibility = $this->getVariableScope()->isGlobal() ? 'global' : 'local';
        return "($globalVisibility.get \${$node->name})";
    }

    private function compileArithmeticExpression(Node\Expr\BinaryOp $node): string
    {
        $binaryOperationName = match (true) {
            $node instanceof Node\Expr\BinaryOp\Plus => 'add',
            $node instanceof Node\Expr\BinaryOp\Minus => 'sub',
            $node instanceof Node\Expr\BinaryOp\Mul => 'mul',
            $node instanceof Node\Expr\BinaryOp\Div => 'div',
        };

        $left = $this->compileNode($node->left);
        $right = $this->compileNode($node->right);
        return "({$this->getExpressionType($node->left)}.$binaryOperationName $left $right)";
    }

    /**
     * @param \PhpParser\Node\Stmt\Function_ $node
     *
     * @return string
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    private function compileFunctionDeclaration(Node\Stmt\Function_ $node): string
    {
        // if is a macro, register it.
        foreach ($node->attrGroups ?? [] as $attrGroup) {
            foreach ($attrGroup->attrs ?? [] as $attr) {
                $qualifiedAttribute = $this->qualifyPhpSymbol($attr->name->toCodeString());
                if ($qualifiedAttribute === 'Wasm\Core\Macro') {
                    $qualifiedMacro = FileScope::current()
                        ->namespace()
                        ->addFunction($this->compileNode($node->name));
                    self::$macros[$qualifiedMacro]
                        = fn(...$params) => sprintf($attr->args[0]->value->value, ...$params);
                    $this->getVariableScope()->declare(
                        $qualifiedMacro,
                        $attr->args[1]->value->value,
                        ''
                    );
                    return '';
                }
            }
        }
        $funcName = FileScope::current()
            ->namespace()
            ->addFunction($this->compileNode($node->name));
        $params = [];
        foreach ($node->params as $param) {
            if (empty($param->type)) {
                throw new RuntimeException("function param must have a type. ".$this->getNodeLine($node));
            }
            $paramName = "\${$param->var->name}";
            $paramType = $this->getWasmType($param->type);
            $this->getVariableScope()
                ->create($funcName, function () use ($paramName, $paramType) {
                    $this->getVariableScope()->declare($paramName, $paramType, '');
                });
            $params[] = "(param $paramName $paramType)";
        }
        $params = implode(' ', $params);
        $returnType = $node->returnType && $node->returnType->toString() !== 'void'
            ? "(result {$this->getWasmType($node->returnType)})"
            : '';

        $this->getVariableScope()->declare(
            $funcName,
            $this->getWasmType($node->returnType),
            $returnType,
        );

        if ($this->isImport($node, "(func $funcName $params $returnType)")) {
            return '';
        }

        $this->setStartIfApplicable($node);
        $export = $this->isExportable($node, $funcName);

        $funcBody = $this->getVariableScope()
            ->create($funcName, function () use ($node) {
                $body = $this->compileNodes($node->stmts);
                return "{$this->getVariableScope()->getDeclarations()} {$body}";
            });

        return "(func $funcName $export $params $returnType $funcBody)";
    }

    private function getNodeLine(Node $node): string
    {
        return "[{$node->getLine()}:{$node->getStartTokenPos()}-{$node->getEndTokenPos()}]";
    }

    private function getWasmType(null|\Stringable $type): string
    {
        return match ((string) $type) {
            'int', 'array', 'i64' => 'i64',
            'float', 'f64' => 'f64',
            'i32' => 'i32',
            'f32' => 'f32',
            'void' => '',
            null => throw new RuntimeException("All parameters must have a type.")
        };
    }

    private function isImport(Node\Stmt\Function_ $node, string $signature): bool
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === 'WasmImport') {
                    $arg = $attr->args[0]->value;
                    $pathName = array_reduce($arg->items, function (string $prev, Node\Expr\ArrayItem $item) {
                        return "$prev \"{$item->value->value}\"";
                    }, "");
                    $pathName = ltrim($pathName);
                    $this->imports[] = "(import $pathName $signature)";
                    return true;
                }
            }
        }

        return false;
    }

    private function setStartIfApplicable(Node\Stmt\Function_ $node): void
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === 'WasmStart') {
                    $funcName = FileScope::current()->qualify($node->name->toString());
                    $this->startFunc = "(start {$funcName})";
                }
            }
        }
    }

    private function isExportable(Node\Stmt\Function_ $node, string $functionName): string
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === 'WasmExport') {
                    $exportName = ltrim($functionName, '$');
                    return "(export \"$exportName\")";
                }
            }
        }
        return '';
    }

    /**
     * @param \PhpParser\Node\Expr\FuncCall $node
     *
     * @return string
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    private function compileFunctionCall(Node\Expr\FuncCall $node): string
    {
        $params = [];
        $funcName = FileScope::current()->qualify($node->name->toString());

        foreach ($node->args as $arg) {
            $params[] = $this->compileNode($arg->value);
        }

        if (array_key_exists($funcName, self::$macros)) {
            return self::$macros[$funcName](...$params);
        }

        $params = implode(' ', $params);
        return "(call $funcName $params)";
    }

    /**
     * @param \PhpParser\Node\Stmt\Return_ $node
     *
     * @return string
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    private function compileReturnStatement(Node\Stmt\Return_ $node): string
    {
        if (is_null($node->expr)) {
            return '';
        }
        $expr = $this->compileNode($node->expr);
        return "(return $expr)";
    }

    /**
     * @return \ErickJMenezes\Phasm\Compiler\Scopes\VariableScope
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    private function getVariableScope(): VariableScope
    {
        return FileScope::current()
            ->namespace()
            ->variableScope();
    }

    private function qualifyPhpSymbol(string $name): string
    {
        return FileScope::current()
            ->namespaceOf($name) . '\\' . $name;
    }
}
