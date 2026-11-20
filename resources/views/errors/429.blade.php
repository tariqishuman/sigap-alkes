@extends('errors.sigap')

@section('code', '429')
@section('title', 'Terlalu banyak permintaan')
@section('message', 'Anda mengirim terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar, lalu coba lagi.')

@section('actions')
    <button type="button" class="btn btn-primary" onclick="location.reload()">Coba lagi</button>
@endsection
