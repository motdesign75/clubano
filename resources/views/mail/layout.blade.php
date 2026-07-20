<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
</head>

<body style="margin:0; padding:0; background:#f3f4f6; font-family:Arial, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:20px;">

<tr>
<td align="center">


<table width="600" cellpadding="0" cellspacing="0"
style="background:white; border-radius:6px; overflow:hidden;">


<tr>
<td style="background:#2954A3; color:white; padding:15px;">


@if(!empty($tenant->logo_url))

<img src="{{ $tenant->logo_url }}"
     style="max-height:40px; display:block; margin-bottom:5px;">

@endif


<strong>

{{ $tenant->name }}

</strong>

</td>
</tr>



<tr>
<td style="padding:20px; font-size:14px; color:#333;">

{!! $body !!}

</td>
</tr>



<tr>
<td style="background:#f9fafb; padding:15px; font-size:12px; color:#666;">

Diese Nachricht wurde gesendet von<br>

<strong>{{ $tenant->name }}</strong><br>

{{ $tenant->email }}<br>

{{ $tenant->zip }} {{ $tenant->city }}

</td>
</tr>


</table>


</td>
</tr>

</table>

</body>
</html>
