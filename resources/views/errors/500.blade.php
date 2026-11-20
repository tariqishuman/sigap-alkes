@extends('errors.layout')

@section('code', '500')
@section('title', 'Terjadi gangguan pada sistem')
@section('message', 'Ada masalah di sisi server. Tim kami sedang memeriksanya. Coba lagi beberapa saat lagi.')

@section('actions')
    <button type="button" class="btn btn-primary" onclick="location.reload()">Coba lagi</button>
    <button type="button" class="btn btn-ghost" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/admin') }}')">Kembali</button>
@endsection
