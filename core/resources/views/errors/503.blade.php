@extends('errors.layout', [
    'status' => "503",
    'title' => "We'll be right back",
    'lede' => "The store is briefly unavailable while we carry out maintenance. Your orders and downloads are safe.",
    'detail' => null,
    'reference' => app(\App\Support\ErrorReference::class)->current(),
])
