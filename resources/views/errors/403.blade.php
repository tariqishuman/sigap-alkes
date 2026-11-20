@extends('errors.layout')

@section('code', '403')
@section('title', 'Akses ditolak')
@section('message', 'Akun Anda tidak punya izin untuk membuka halaman ini. Hubungi admin IPSRS jika menurut Anda ini keliru.')

@section('actions')
    <a href="{{ url('/admin') }}" class="btn btn-primary">Ke beranda</a>
    <button type="button" class="btn btn-ghost" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/admin') }}')">Kembali</button>
@endsection
