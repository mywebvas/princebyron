@extends('errors.layout', [
    'status' => "500",
    'title' => "Something went wrong on our side",
    'lede' => "This isn't your fault. The problem has been logged and we're looking at it. Nothing was charged.",
    'detail' => null,
    'reference' => app(\App\Support\ErrorReference::class)->current(),
])
