<?php
if (@$_REQUEST['customerid']) {

    $cid = (int)$_REQUEST['customerid'];
    $multi = @$_REQUEST['multi'];
    if ($multi) {
        $type = 'points ps 0.2';
    } else {
        $type = 'lines';
    }

    $plotfile = "/tmp/exp-gnuplot-".getmypid().".gnuplot";
    $csvfile = "/tmp/exp-input-data.".getmypid().".csv";
    $output = "/tmp/exp-output-".getmypid().".svg";
    $errlog = "/tmp/exp-errlog-".getmypid().".txt";

    $match='.*_(\\d{4})(\\d\\d)(\\d\\d)-(\\d\\d)(\\d\\d):.*'.$cid.'[^\\d]+([0-9.]+).*';
    $repl='$1-$2-$3 $4:$5:00,$6';
    system("egrep -r '^  *$cid.*(MST|MDT)' ~mdriscoll/spurge/ | egrep -v 'done|requested|stalled|active' "
        . " | grep -v '.*|.*|.*|.*|' "
        . " | perl -ne 's/$match/$repl/ and print' | sort > $csvfile 2>> $errlog");

    $pf = fopen($plotfile, "w");
    $plotcmds = <<<EOT
        set datafile separator ","
        set terminal svg size 800,500
        set title font ",18"
        set title "Customer $cid Export Progress"
        set xdata time
        set timefmt "%Y-%m-%d %H:%M:%S"
        set format x "%m/%d"
        set format y '%.0f'
        set key off
        set grid
        plot "$csvfile" using 1:2 with $type lw 2 lt 2
EOT;
    fwrite($pf, $plotcmds);
    fclose($pf);

    system("gnuplot < $plotfile > $output 2>> $errlog");

    header("Content-Type: image/svg+xml");
    $filename = "$cid-" . strftime("%Y%m%d%H%M%S") . ".svg";
    header("Content-Disposition: inline; filename=\"$filename\"");
    $f = fopen($output, "r");
    fpassthru($f);

    unlink($plotfile);
    unlink($csvfile);
    unlink($output);
    unlink($errlog);

    return;
}

?>
<html>
<head>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
</head>
<body>
<div class="container" style="max-width: 600px">
<h2>Graph a customer's export progress</h2>
<form style="padding: 10" method="GET" action="<?php echo filter_input(INPUT_SERVER, 'PHP_SELF', FILTER_SANITIZE_URL); ?>">
    <div class="form-group">
        <label for="input-cid">Customer ID</label>
        <input id="input-cid" name="customerid" class="form-control">
    </div>
    <div class="checkbox">
        <label>
            <input id="input-multi" type="checkbox" name="multi">
            multi-worker (try this if the graph is a mess of lines)
        </label>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-default">Submit</button>
    </div>
</form>
</div>
</body>
</html>
