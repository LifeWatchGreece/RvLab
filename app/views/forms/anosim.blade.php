<?php $function = "anosim"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'anosim_form','style'=>'display:none')) }}        

{{ form_function_about('anosim',$tooltips) }}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('anosim-box','Select community data file from workspace files',$tooltips,$workspace_files) }} 
    {{ form_dropdown('anosim-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','standardize','pa','chi.square','horn','hellinger','log'),'',$tooltips) }}                                     
    {{ form_checkbox('taxondive-transpose','Check to transpose matrix','transpose',true,$tooltips) }}    
    {{ form_radio_files('anosim-box2','Select factor file:',$tooltips,$workspace_files) }}         
    {{ form_dropdown('anosim-column_select','Select Column in Factor File:',array(),'',$tooltips) }}
    
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_textinput('anosim-permutations','Permutations:','999',$tooltips) }}    
    {{ form_dropdown('anosim-method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean',$tooltips) }} 
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}

<script type="text/javascript">        
    
    $(document).on('change', '#anosim_form input[name="box2"]', function(){
        var selectedValue = $("#anosim_form input[name='box2']:checked").val();
        // If user selects another file call the function that updates the dropdowns
        loadCsvHeaders(selectedValue,"anosim_form","column_select");          
    });
    
</script>                    

<?
    // Initially, we assume that the first in row file is selected and that file will be used to retrieve the headers
    $count = 0;
    foreach($workspace_files as $file){
        if($count == 0){
            echo "<script type='text/javascript'>loadCsvHeaders('".$file->filename."','anosim_form','column_select');</script>";
        }
        $count++;
    }        
?>