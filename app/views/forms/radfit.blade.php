<?php $function = "radfit"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'radfit_form','style'=>'display:none')) }}               

    {{ form_function_about('radfit',$tooltips) }}
    <br>
    <div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('radfit-box','Select community data as a symmetric square matrix from loaded files',$tooltips,$workspace_files) }}    
    {{ form_dropdown('radfit-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'',$tooltips) }}                                   
    {{ form_checkbox('radfit-transpose','Check to transpose matrix','transpose',true,$tooltips) }}
    <br>
    <div style="color: blue; font-weight: bold">Parameters</div>
    {{ form_textinput('radfit-column_radfit','Select Column from community data matrix:','0',$tooltips) }}

    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}