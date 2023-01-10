<footer id="footer">
    <hr style="margin-bottom: 4px;">
    <p id='mi-texto'>Nota Credito No: {{$resolution->prefix}} - {{$request->number}} - Fecha y Hora de Generacion: {{$date}} - {{$time}}<br> CUDE: <strong>{{$cufecude}}</strong></p>
    @isset($request->foot_note)
        <p id='mi-texto-1'><strong>{{$request->foot_note}}</strong></p>
    @endisset
</footer>
