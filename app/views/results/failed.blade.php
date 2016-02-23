<div class="top-label">
    Job{{ $job->id }} Information/Results <span style="color: #ef7c61; margin-left: 20px; font-weight: normal">({{ $function }})</span>
    @if($function != 'bict')
        <img src="{{ asset('images/script1.png') }}" style="width: 25px; float: right" class="view-script-icon" title="View R script">
    @endif
    <a href="{{ url('/') }}" style="float:right; margin-right: 10px; font-size: 19px" title="Home Page"><span class="glyphicon glyphicon-home" aria-hidden="true"></span></a>
</div>
<br>

<div style='padding: 20px'>
Job execution failed. {{ $errorString }}
</div>

<img src="{{ asset('images/loading.gif') }}" style="display:none" id="loading-image" />

<div class="panel panel-default" id="r-script-panel" style="display: none; max-width:500px">
    <div class="panel-heading">
        <strong>R script</strong>
        <span class="glyphicon glyphicon-remove" style="float:right; color: red" aria-hidden="true" id="close-r-panel"></span>
    </div>
  <div class="panel-body" style="max-height: 350px; overflow: auto">
    
  </div>
</div>

<script type="text/javascript">       
         
$('#close-r-panel').click(function(){
    $('#r-script-panel').hide();
});       
         
$('.view-script-icon').click(function(){    
    $('#loading-image').center().show();  
    $.ajax({
        url : '{{ url("storage/get_r_script/".$job->id) }}',
        type: "GET",
        dataType : 'json',
        success:function(data, textStatus, jqXHR) 
        {
            $('#r-script-panel .panel-body').empty();
            for(var i = 0; i < data.length; i++) {
                $('#r-script-panel .panel-body').append(data[i]+"<br>");
            }
            $('#r-script-panel').center().show();          
        },
        error: function(jqXHR, textStatus, errorThrown) 
        {
            switch (jqXHR.status) {
                case 400: // Form validation failed
                    alert("R script could not be found");
                    break;
                 case 401: // Unauthorized access
                    alert("You don't have access to this R script.");
                    break;
                 case 500: // Unexpected error
                    alert("R script could not be retrieved.");
                    break;
            }
        },
        complete: function(){
          $('#loading-image').hide();
        }
    });  
});
     
       
       
</script>       