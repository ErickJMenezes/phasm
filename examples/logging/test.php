<?php

use Wasm\Core\WasmExport;
use Wasm\Core\WasmImport;
use Wasm\Core\WasmStart;

#[WasmImport(['console', 'log'])]
function logValue(int $value): void {}

#[WasmExport]
function sum(int $a, int $b): int
{
    $c = $a + $b;
    return $c;
}

#[WasmStart]
function main(): void
{
    logValue(sum(1, 2));
}
