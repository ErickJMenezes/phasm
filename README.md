# Phasm

A PHP to WebAssembly WAT compiler.

This is for demonstration purposes only. Only a small subset of php is supported.

## Example

Example php code:
```php
<?php

#[WasmImport(['console', 'log'])]
function logSum(int $value): void {}

#[WasmStart]
function main(): void
{
    $a = 10;
    $b = 100;
    $c = $a + $b;
    logSum($c);
}
```

Output WAT code:
```webassembly
(module 
    (import "console" "log" (func $logSum (param i64)))
    (func $main 
        (local $a i64)
        (local $b i64) 
        (local $c i64)
        (local.set $a (i64.const 10)) 
        (local.set $b (i64.const 100))
        (local.set $c (i64.add (local.get $a) (local.get $b)))
        (local.get $c)
        (call $logSum)
    ) 
    (start $main)
)
```

Copy the code above and paste it here:
https://developer.mozilla.org/en-US/docs/WebAssembly/Reference/Variables/Local#try_it

## How to use
To compile your source code, use this command:
```shell
./bin/phasm <file> <output>
```

## Supported features
- Only int (i64) and float (f64) types are supported.
- Math `+ - * /` operations are supported
- Variable declarations
- Function declarations (No return value supported yet)
- Import instruction with `#[WasmImport]` Attribute
- Start function instruction with `#[WasmStart]` Attribute
- Export functions `(export "foo1")`
- Support function return types `(result i32)`
- Support function parameters

## Future
- Boolean expressions
- Strings
- For loops
- While loops
- if, else if, and else statements
- class with properties (simple struct)
- class with methods (enhanced structs)
- inheritance
