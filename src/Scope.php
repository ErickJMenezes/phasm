<?php

namespace ErickJMenezes\Phasm;

use Closure;

class Scope
{
    private const GLOBAL_SCOPE = '__global__';

    /**
     * @var array<string, array<string>>
     */
    private array $scopes = [
        self::GLOBAL_SCOPE => [],
    ];

    private string $currentScope = self::GLOBAL_SCOPE;
    private string $previousScope = self::GLOBAL_SCOPE;

    public function create(string $name, Closure $callback): string
    {
        $name = $name === self::GLOBAL_SCOPE ? $name : md5($name);
        $this->previousScope = $this->currentScope;
        $this->scopes[$this->currentScope = $name] ??= [];
        try {
            return $callback();
        } finally {
            $this->currentScope = $this->previousScope;
        }
    }

    public function global(Closure $callback): string
    {
        return $this->create(self::GLOBAL_SCOPE, $callback);
    }

    public function isGlobal(): bool
    {
        return $this->currentScope === self::GLOBAL_SCOPE;
    }

    public function hasDeclared(string $name): bool
    {
        return array_key_exists($name, $this->scopes[$this->currentScope]);
    }

    public function declare(string $name, string $type, string $declaration): void
    {
        $this->scopes[$this->currentScope][$name] = ['type' => $type, 'rawDeclaration' => $declaration];
    }

    public function getDeclarations(): string
    {
        $declarations = array_map(function (array $data) {
            return $data['rawDeclaration'];
        }, $this->scopes[$this->currentScope]);

        return implode(' ', $declarations);
    }

    public function getType(string $name): string
    {
        if (!$this->hasDeclared($name)) {
            throw new \RuntimeException("The variable $name is not declared.");
        }

        return $this->scopes[$this->currentScope][$name]['type'];
    }
}
