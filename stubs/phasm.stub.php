<?php

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

/**
 * 32-bit Integer.
 */
class i32 {}

/**
 * 64-bit Integer.
 */
class i64 {}

/**
 * 32-bit floating point number.
 */
class f32 {}

/**
 * 64-bit floating point number.
 */
class f64 {}
