@unless($admin) visitante @endunless
@isset($missing) tem @endisset
@for($i = 1; $i <= 3; $i++){{ $i }}@endfor
@php($total = 2 + 2)
{{ $total }}
@php
$dobro = $total * 2;
@endphp
{{ $dobro }}
{!! $html !!}
@{{ literal }}
contato@forja.test
<form>@csrf</form>
@if(str_contains($texto, ')')) tem parêntese @endif
