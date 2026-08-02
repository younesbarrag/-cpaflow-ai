@props(['action', 'method' => 'POST', 'confirmMessage' => 'Are you sure?', 'label', 'class' => ''])

<form method="POST" action="{{ $action }}" class="inline-block" x-data>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <button
        type="submit"
        onclick="return confirm('{{ $confirmMessage }}')"
        class="{{ $class }}"
    >
        {{ $label }}
    </button>
</form>
