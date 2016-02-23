<style type="text/css">
    .reg_period_table td {
        padding: 20px 40px 20px 0px;
    }
</style>


<div style="font-weight: bold; font-size: 20px; text-align: center; margin: 20px">R vLab Registration <span style="font-weight: normal; color: gray">(Select and submit. That's it!)</span></div>
<div style="margin-left: 30px">

    {{ Form::open(array('url'=>'registration')) }}
    
    <table class="reg_period_table">
        <tr>
            <td>Please, provide me access to R vLab for:</td>
            <td>
                <div class="radio">
                    <label>
                      <input type="radio" name="registration_period" id="period_day" value="day" checked>
                      1 day
                    </label>
                  </div>
                  <div class="radio">
                    <label>
                      <input type="radio" name="registration_period" id="period_week" value="week">
                      1 week
                    </label>
                  </div>
                  <div class="radio">
                    <label>
                      <input type="radio" name="registration_period" id="period_month" value="month">
                      1 month
                    </label>
                  </div>
                  <div class="radio">
                    <label>
                      <input type="radio" name="registration_period" id="period_semester" value="semester">
                      6 month
                    </label>
                </div>
            </td>
            <td><button type='submit' class='btn btn-default'>Register</button></td>
        </tr>
    </table>    

    {{ Form::close() }}

</div>

<div style="color: gray; margin: 20px">
    <strong>Note:</strong> Please, try to select the registration period that represents in  the best way your intentions. For example,
    if you are requesting short-term access only to try or check out the R vLab functionality, select the "1 day". Making such a selection
    help us to provide a minimum quality of service to as many users as possible.
</div>