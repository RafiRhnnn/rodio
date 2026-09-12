@extends('layouts.admin')

@section('title', 'Riwayat Konversi — Admin')
@section('heading', 'Riwayat Konversi')
@section('subheading', 'Seluruh konversi pengguna dengan filter dan retry.')

@php
    $size = fn ($bytes) => $bytes ? number_format($bytes / 1048576, 1).' MB' : '—';
    $clock = fn ($s) => $s === null ? '—' : gmdate('i:s', (int) $s);
    $stamp = fn ($t) => $t?->translatedFormat('d M Y H:i') ?? '—';
@endphp

@section('content')
    @include('admin.conversions._filters')
    @include('admin.conversions._table')
    @include('partials.confirm-modal')
@endsection
