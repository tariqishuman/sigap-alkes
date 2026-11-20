@extends('errors.sigap')

@section('code', '401')
@section('title', 'Silakan masuk dulu')
@section('message', 'Halaman ini hanya untuk pengguna yang sudah masuk ke SIGAP-Alkes.')

@section('actions')
    <a href="{{ url('/admin/login') }}" class="btn btn-primary">Masuk</a>
@endsection
