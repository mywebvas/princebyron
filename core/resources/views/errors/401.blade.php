@extends('errors.layout', [
    'status' => "401",
    'title' => "Please sign in first",
    'lede' => "This page needs an account. Signing in takes a moment and your bag is kept.",
    'detail' => null,
    'reference' => null,
])
