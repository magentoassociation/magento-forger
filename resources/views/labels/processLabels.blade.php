@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">

                <div class="card-body">
                    @foreach (['success' => 'alert-success', 'warning' => 'alert-warning', 'error' => 'alert-danger'] as $flashKey => $alertClass)
                        @if (session($flashKey))
                            @php $flash = session($flashKey); @endphp
                            <div class="alert {{ $alertClass }}" role="alert">
                                {{ $flash['header'] }}<br>
                                Created Labels: {{ number_format($flash['created']) }}<br>
                                Renamed Labels: {{ number_format($flash['renamed']) }}<br>
                                Skipped Remaps: {{ number_format(count($flash['skipped'])) }}
                                @foreach ($flash['skipped'] as $skipped)
                                    <br>{{ $skipped }}
                                @endforeach
                                @foreach ($flash['errors'] as $error)
                                    <br>{{ $error }}
                                @endforeach
                            </div>
                        @endif
                    @endforeach

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('labels-uploadLabels') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="label_sheet" class="form-label">{{ __('GitHub Labels Excel Sheet') }}</label>
                            <input id="label_sheet" type="file" class="form-control @error('label_sheet') is-invalid @enderror" name="label_sheet" required>

                            @error('label_sheet')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-primary">
                                {{ __('Upload and Process') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
