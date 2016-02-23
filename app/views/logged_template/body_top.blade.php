<div class="container">    
    
   <div class="panel panel-default">
        <div class="panel-heading">
            @if(isset($app))                
                <span style='font-size: 16px; font-weight: bold'>R vLab</span>                                            
            @else
                <span style='font-size: 16px; font-weight: bold'></span>
            @endif
            <a href='{{ url('logout') }}' style='float:right'>Logout</a>
        </div>       
        <div class="panel-body">