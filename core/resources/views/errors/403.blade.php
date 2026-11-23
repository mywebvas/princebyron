@extends('errors.layout', [
    'status' => "403",
    'title' => "You don't have access to this",
    'lede' => "This area is either private or not part of your account. If you believe that's wrong, sign in again with the account that made the purchase.",
    'detail' => null,
    'reference' => null,
])
