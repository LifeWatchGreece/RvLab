<?php $function = "mantel"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'mantel_form','style'=>'display:none')) }}        
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('mantel-box','Select a dissimilarity structure as produced by dist from workspace files',$tooltips,$workspace_files) }}
    {{ form_dropdown('mantel-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','standardize','pa','chi.square','horn','hellinger','log'),'',$tooltips) }}                                             
    {{ form_checkbox('mantel-transpose','Check to transpose matrix','transpose',true,$tooltips) }}
    {{ form_radio_files('mantel-box2','Select a dissimilarity structure as produced by dist:',$tooltips,$workspace_files) }}              
    
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_textinput('mantel-permutations','Permutations','999',$tooltips) }}    
    {{ form_dropdown('mantel-method_select','Method:',array('pearson','spearman','canberra'),'spearman',$tooltips) }}         
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}