(module 
(import "console" "log" (func $__logValue (param $value i64) )) 
(func $__sum (export "__sum") (param $a i64) (param $b i64) (result i64)   (local $c i64) (local.set $c (i64.add (local.get $a) (local.get $b)))
(return (local.get $c)))
(func $__main     (call $__logValue (call $__sum (i64.const 1) (i64.const 2)))) 
(start $__main))