<?php

namespace ErickJMenezes\Phasm\Compiler\Scopes;

class NamespaceScope
{
    public function __construct(
        public readonly string $name,
        private array $functions = [],
        private VariableScope $variableScope = new VariableScope(),
    ) {}

    public function addFunction(string $name): string
    {
        return $this->functions[$name] = $this->qualify($name);
    }

    public function hasFunction(string $name): string|false
    {
        return $this->functions[$name] ?? false;
    }

    private function qualify(mixed $name): string
    {
        return str_replace('\\', '_', "\${$this->name}\\{$name}");
    }

    public function variableScope(): VariableScope
    {
        return $this->variableScope;
    }
}
