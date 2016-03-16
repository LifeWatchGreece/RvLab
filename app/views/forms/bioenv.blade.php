<?php $function = "bioenv"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'bioenv_form','style'=>'display:none')) }}        

{{ form_function_about('bioenv',$tooltips) }}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('bioenv-box','Select community data as a symmetric square matrix from loaded files',$tooltips,$workspace_files) }}       
    {{ form_dropdown('bioenv-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'',$tooltips) }}           
    {{ form_checkbox('bioenv-transpose','Check to transpose matrix','transpose',true,$tooltips) }} 
    {{ form_radio_files('bioenv-box2','Select enviromental variable factor file',$tooltips,$workspace_files) }}        
    
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_dropdown('bioenv-method_select','Method',array('spearman','pearson','canberra'),'spearman',$tooltips) }}     
    {{ form_dropdown('bioenv-index','index:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean',$tooltips) }}     
    {{ form_textinput('bioenv-upto','upto','2',$tooltips) }}  
    {{ form_dropdown('bioenv-trace','trace',array('FALSE','TRUE'),'FALSE',$tooltips) }} 
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}