@extends('errors.layout')

@section('code', '404')
@section('title', 'Alat tidak ditemukan')
@section('message', 'Kode QR alat kesehatan tidak valid atau tidak terdaftar di sistem.')
@section('note', 'Pastikan label QR pada alat masih terbaca dengan jelas, atau hubungi unit IPSRS.')

@section('actions')
    <a href="{{ url('/admin') }}" class="btn btn-primary">Ke beranda</a>
@endsection
