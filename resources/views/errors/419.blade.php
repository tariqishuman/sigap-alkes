@extends('errors.sigap')

@section('code', '419')
@section('title', 'Sesi Anda berakhir')
@section('message', 'Halaman ini terlalu lama dibiarkan terbuka, jadi demi keamanan sesinya ditutup. Muat ulang halaman, lalu coba lagi.')

@section('actions')
    <button type="button" class="btn btn-primary" onclick="location.reload()">Muat ulang</button>
    <a href="{{ url('/admin/login') }}" class="btn btn-ghost">Masuk lagi</a>
@endsection
