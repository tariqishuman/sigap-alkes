@extends('errors.layout')

@section('code', '404')
@section('title', 'Halaman tidak ditemukan')
@section('message', 'Alamat yang Anda buka tidak ada atau sudah dipindahkan. Periksa kembali tautannya.')
@section('note', 'Mencari alat? Scan ulang label QR pada alat untuk melihat statusnya.')

@section('actions')
    <a href="{{ url('/admin') }}" class="btn btn-primary">Ke beranda</a>
    <button type="button" class="btn btn-ghost" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/admin') }}')">Kembali</button>
@endsection
