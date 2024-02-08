<?php

namespace Wasm\Core;

use Attribute;

#[Attribute]
class WasmStart
{
}

#[Attribute]
class WasmExport
{
}

#[Attribute]
class WasmImport
{
    public function __construct(
        public array $name,
    ) {}
}

#[Attribute]
class Macro
{
    public function __construct(
        public string $macro,
        public string $type,
    ) {}
}

#[Macro('(i64.load %s)', 'i64')]
function i64_load(int $value): int {}

#[Macro('(i64.store %s %s)', 'i64')]
function i64_store(int $offset, int $value): void {}
