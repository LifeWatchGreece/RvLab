<?php $function = "bict"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'bict_form','style'=>'display:none')) }}        

{{ form_function_about('bict',$tooltips) }}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('bict-box','Select community data as a symmetric square matrix from loaded files',$tooltips,$workspace_files) }}         
    {{ form_dropdown('bict-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'',$tooltips) }}             
    {{ form_checkbox('taxondive-transpose','Check to transpose matrix','transpose',true,$tooltips) }}         
    {{ form_radio_files('bict-box2','Select classification table with a row for each species or other basic taxon, and columns for identifiers of its classification at higher levels',$tooltips,$workspace_files) }}         
    
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_dropdown('bict-species_family_select','Species or Family',array('species','family'),'species',$tooltips) }}            
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}