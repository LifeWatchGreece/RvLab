<style type="text/css">
    #technical_doc_table tr td {
        text-align: left;
    }
</style>

<p>Scientific documentation of each function used in R vLab</p>

<table class="table table-bordered" id="technical_doc_table">
    <thead>
        <th>Function</th>
        <th>Link</th>
    </thead>
    <tbody>
        @foreach($links as $function => $link)
        <tr>
            <td>{{ $function }}</td>
            <td><a href="{{ $link }}">{{ $link }}</a></td>
        </tr>
        @endforeach
    </tbody>
</table>