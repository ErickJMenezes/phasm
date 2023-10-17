<?php

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
