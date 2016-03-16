<?php $function = "taxondive"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'taxondive_form','style'=>'display:none')) }}        

{{ form_function_about('taxondive',$tooltips) }}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('taxondive-box','Select community data matrix from workspace files',$tooltips,$workspace_files) }}     
    {{ form_dropdown('taxondive-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','standardize','pa','chi.square','horn','hellinger','log'),'',$tooltips) }}   
    {{ form_checkbox('taxondive-transpose','Check to transpose matrix','transpose',true,$tooltips) }}     
    {{ form_radio_files('taxondive-box2','Select taxonomic distances among taxa for community data defined above (dist object)',$tooltips,$workspace_files) }}              
    {{ form_radio_files('taxondive-box3','Select factor file (Optional):',$tooltips,$workspace_files) }}                            
    {{ form_dropdown('taxondive-column_select','Select Column in Factor File:',array(),'',$tooltips) }}   
    
    
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_dropdown('taxondive-match_force','match.force',array('FALSE','TRUE'),'FALSE',$tooltips) }}    
    {{ form_dropdown('taxondive-deltalamda','Taxondive parameter:',array('Delta','Lamda'),'',$tooltips) }}
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}

<script type="text/javascript">        
    
    $(document).on('change', '#taxondive_form input[name="box3"]', function(){
        var selectedValue = $("#taxondive_form input[name='box3']:checked").val();
        // If user selects another file call the function that updates the dropdowns
        loadCsvHeaders(selectedValue,"taxondive_form","column_select");          
    });
    
</script>                    

<?
    // Initially, we assume that the first in row file is selected and that file will be used to retrieve the headers
    $count = 0;
    foreach($workspace_files as $file){
        if($count == 0){
            echo "<script type='text/javascript'>loadCsvHeaders('".$file->filename."','taxondive_form','column_select');</script>";
        }
        $count++;
    }        
?>