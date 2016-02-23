<!doctype html>
<html lang="en">
<head> 
	<meta charset="UTF-8">
        {{ $head }}
	<title>{{ $title }}</title>	
</head>
<body>
        {{ $body_top }}
        {{ $content }}
        {{ $body_bottom }}
</body>
</html>
@if(Session::has('toastr'))
    <? $toastr = Session::get('toastr') ?>
    <script type="text/javascript">
        switch('{{ $toastr[0] }}'){
            case 'info':
                toastr.info('{{ $toastr[1] }}');
                break;
            case 'success':
                toastr.success('{{ $toastr[1] }}');
                break;
            case 'warning':
                toastr.warning('{{ $toastr[1] }}');
                break;
            case 'error':
                toastr.error('{{ $toastr[1] }}');
                break;                
        }
    </script>
@endif
