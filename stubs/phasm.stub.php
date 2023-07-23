<?php

#[Attribute]
class WasmStart
{

}

#[Attribute]
class WasmImport
{
    public function __construct(
        public string|array $name,
    ) {}
}

