@extends('errors.sigap')

@section('code', '503')
@section('title', 'Sedang pemeliharaan')
@section('message', 'Seperti alat kesehatan, SIGAP-Alkes juga perlu dikalibrasi. Sistem sedang dalam pemeliharaan rutin dan akan segera kembali.')
@section('note', 'Halaman ini akan memuat ulang otomatis setiap 60 detik.')
@section('refresh', '60')

@section('actions')
    <button type="button" class="btn btn-primary" onclick="location.reload()">Muat ulang</button>
@endsection
