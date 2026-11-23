@extends('errors.layout', [
    'status' => "402",
    'title' => "Payment could not be completed",
    'lede' => "The payment was not taken. You can try again, or choose bank transfer at checkout.",
    'detail' => null,
    'reference' => null,
])
