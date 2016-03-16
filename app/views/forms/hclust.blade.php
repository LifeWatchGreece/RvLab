<?php $function = "hclust"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'hclust_form','style'=>'display:none')) }}        

{{ form_function_about('hclust',$tooltips) }}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('hclust-box','Select a dissimilarity structure as produced by dist from loaded files',$tooltips,$workspace_files) }}  
    {{ form_radio_files('hclust-box2','Select factor file (Optional)',$tooltips,$workspace_files) }}                
    {{ form_dropdown('hclust-column_select','Select Column in Factor File:',array(),'',$tooltips) }} 
    
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_dropdown('hclust-method_select','Method',array('ward.D','ward.D2','single','complete','average','mcquitty','median','centroid'),'ward.D',$tooltips) }} 
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}

<script type="text/javascript">        
    
    $(document).on('change', '#hclust_form input[name="box2"]', function(){
        var selectedValue = $("#hclust_form input[name='box2']:checked").val();
        // If user selects another file call the function that updates the dropdowns
        loadCsvHeaders(selectedValue,"hclust_form","column_select");          
    });
    
</script>                    

<?
    // Initially, we assume that the first in row file is selected and that file will be used to retrieve the headers
    $count = 0;
    foreach($workspace_files as $file){
        if($count == 0){
            echo "<script type='text/javascript'>loadCsvHeaders('".$file->filename."','hclust_form','column_select');</script>";
        }
        $count++;
    }        
?>