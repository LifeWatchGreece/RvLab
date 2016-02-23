
{{ Form::open(array('url'=>'login')) }}    

    <div class="row">
        <div class="form-group">
            <label for="email" class="col-sm-4 control-label" style="text-align: right">Username</label>
            <div class="col-sm-4">
              {{ Form::text('username',"",array('class'=>'form-control')) }}
              {{ $errors->first('username',"<span style='color:red'>:message</span>") }}
            </div>
        </div>    
    </div>
    <br>
    <div class="row">
        <div class="form-group">
            <label for="password" class="col-sm-4 control-label" style="text-align: right">Password</label>
            <div class="col-sm-4">
              {{ Form::password('password',array('class'=>'form-control')) }}
              {{ $errors->first('password',"<span style='color:red'>:message</span>") }}
            </div>
        </div> 
    </div>
    <br>
    @if(Session::has('auth_error'))
    <div class="row">
        <div class="form-group">
            <label for="password" class="col-sm-4 control-label" style="text-align: right"></label>
            <div class="col-sm-4">
                <span style='color: red'>{{ Session::get('auth_error') }}</span>
            </div>
        </div>         
    </div>
    <br>
    @endif
    <div style='text-align: center'>
        <button class='btn btn-primary'>Login</button>
    </div>

{{ Form::close() }}