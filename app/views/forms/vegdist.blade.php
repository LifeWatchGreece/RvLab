<?php $function = "vegdist"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'vegdist_form','style'=>'display:none')) }}               

    {{ form_function_about('vegdist',$tooltips) }}
    <br>
    <div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('vegdist-box','Select community data matrix from loaded files.',$tooltips,$workspace_files) }}     
    {{ form_dropdown('vegdist-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'',$tooltips) }}   
    {{ form_checkbox('vegdist-transpose','Check to transpose matrix','transpose',true,$tooltips) }}
    <br>
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_dropdown('vegdist-method_select','Method:',array('euclidean','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean',$tooltips) }}      
    {{ form_dropdown('vegdist-binary_select','Binary',array('FALSE','TRUE'),'FALSE',$tooltips) }}
    {{ form_dropdown('vegdist-diag_select','diag',array('FALSE','TRUE'),'FALSE',$tooltips) }}
    {{ form_dropdown('vegdist-upper_select','upper',array('FALSE','TRUE'),'FALSE',$tooltips) }}
    {{ form_dropdown('vegdist-na_select','na.rm ',array('FALSE','TRUE'),'FALSE',$tooltips) }}      
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}