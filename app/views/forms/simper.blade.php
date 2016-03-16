<?php $function = "simper"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'simper_form','style'=>'display:none')) }}       

{{ form_function_about('simper',$tooltips) }}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('simper-box','Select community data file from workspace files',$tooltips,$workspace_files) }}          
    {{ form_checkbox('simper-transpose','Check to transpose matrix','transpose',true,$tooltips) }}  
    {{ form_radio_files('simper-box2','Select factor file',$tooltips,$workspace_files) }}              
    {{ form_dropdown('simper-column_select','Select Column in Factor File:',array(),'',$tooltips) }}
    
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_textinput('simper-permutations','Permutations: ','0',$tooltips) }}  
    {{ form_dropdown('simper-trace','Trace',array('FALSE','TRUE'),'FALSE',$tooltips) }}
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}

<script type="text/javascript">        
    
    $(document).on('change', '#simper_form input[name="box2"]', function(){
        var selectedValue = $("#simper_form input[name='box2']:checked").val();
        // If user selects another file call the function that updates the dropdowns
        loadCsvHeaders(selectedValue,"simper_form","column_select");          
    });
    
</script>                    

<?
    // Initially, we assume that the first in row file is selected and that file will be used to retrieve the headers
    $count = 0;
    foreach($workspace_files as $file){
        if($count == 0){
            echo "<script type='text/javascript'>loadCsvHeaders('".$file->filename."','simper_form','column_select');</script>";
        }
        $count++;
    }        
?>

