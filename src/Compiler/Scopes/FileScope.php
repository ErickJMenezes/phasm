<?php

namespace ErickJMenezes\Phasm\Compiler\Scopes;

use RuntimeException;

/**
 * Class FileScope.
 *
 * The purpose of this scope is to provide a file scope for the compiler.
 * It will store the current file, and all namespaces declared inside the php file.
 * It will also store all uses declared inside the file for further processing.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
class FileScope
{
    private static array $files = [];
    private static ?self $currentFile;

    private NamespaceScope $activeNamespace;

    /**
     * @param string                                                     $fileName
     * @param array<string>                                              $uses
     * @param array<\ErickJMenezes\Phasm\Compiler\Scopes\NamespaceScope> $namespaces
     */
    public function __construct(
        public readonly string $fileName,
        private array $uses = [],
        private array $namespaces = [],
    ) {
        // every file should start with a namespace as a fallback.
        $this->activeNamespace = new NamespaceScope('\\');
    }

    public static function create(string $fileName, callable $callback): string
    {
        $oldFileScope = self::$currentFile ?? null;
        self::$currentFile = self::$files[md5($fileName)] ??= new self($fileName,);
        try {
            return $callback();
        } finally {
            self::$currentFile = $oldFileScope;
        }
    }

    public static function current(): self
    {
        if (empty(self::$currentFile)) {
            throw new RuntimeException('No file scope found.');
        }
        return self::$currentFile;
    }

    public function addUse(string $use): void
    {
        $this->uses[] = $use;
    }

    public function namespace(?string $namespace = null, callable $callback = null): string|NamespaceScope
    {
        if (is_null($namespace)) {
            return $this->activeNamespace;
        }
        $oldNamespace = $this->activeNamespace;
        try {
            $this->activeNamespace = $this->namespaces[$namespace] ??= new NamespaceScope($namespace);
            return $callback($this->activeNamespace);
        } finally {
            $this->activeNamespace = $oldNamespace;
        }
    }

    public function qualify(string $name, ?string $namespace = null): string
    {
        // se for um namespace, adicionar o nome do namespace ao nome
        if ($func = $this->namespace($namespace)->hasFunction($name)) {
            return $func;
        }
        // senão, buscar no $this->uses e encontre o use que a string termina com o $name
        foreach ($this->uses as $use) {
            if (str_ends_with($use, $name)) {
                return str_replace('\\', '_', "\${$use}\\{$name}");
            }
        }
        return $name;
    }

    public function namespaceOf(string $name): string
    {
        if ($this->namespace()->hasFunction($name)) {
            return $this->namespace()->name;
        }

        foreach ($this->uses as $use) {
            if (str_ends_with($use, $name)) {
                return str_replace("\\$name", '', $use);
            }
        }

        return $this->namespace()->name ?? "\\";
    }
}
