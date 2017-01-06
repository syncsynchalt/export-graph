<?php

$selfurl = filter_input(INPUT_SERVER, 'PHP_SELF', FILTER_SANITIZE_URL);
$known_multis = array(63944674, 37675790, 2001715704, 2095470576, 25743292,
        60240330, 64232826, 2138023554, 25380546, 2075789940, 1062714379, );

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
        #set format x "%m/%d"
        set format y '%.0f'
        set key off
        set grid
        plot "$csvfile" using 1:2 with $type lw 2 lt 2
EOT;
    fwrite($pf, $plotcmds);
    fclose($pf);

    if (filesize($csvfile) === 0) {
        echo "Customer $cid not found\n";
    } else {
        system("gnuplot < $plotfile > $output 2>> $errlog");

        header("Content-Type: image/svg+xml");
        $filename = "$cid-" . strftime("%Y%m%d%H%M%S") . ".svg";
        header("Content-Disposition: inline; filename=\"$filename\"");
        $f = fopen($output, "r");
        fpassthru($f);
    }

    unlink($plotfile);
    unlink($csvfile);
    unlink($output);
    unlink($errlog);

    return;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet"
    href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"
    integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u"
    crossorigin="anonymous">
</head>
<title>Export Progress Grapher</title>
<body>
<div class="container" style="max-width: 6000px">
<div class="row">
    <h2>Graph a customer's export progress</h2>
    <div class="col-sm-offset-1 col-sm-8">
        <form style="padding: 10" method="GET" action="<?= $selfurl; ?>">
            <div id="group-cid" class="form-group">
                <label for="input-cid">Customer ID</label>
                <input id="input-cid" name="customerid" class="form-control" required pattern="\d+"
                    onkeyup="
                        var cl = document.getElementById('group-cid').classList;
                        if (this.checkValidity && cl) {
                            this.checkValidity() ? cl.remove('has-error') : cl.add('has-error');
                        }
                    ">
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
</div>
<div class="row">
    <h3>List of active customer ids</h3>
    <div class="col-sm-offset-1 col-sm-8">
        <ul class="list-unstyled">
        <?php
            $perl = '$p=1 if /Workers ordered by percent done/; $p=0 if /rows\)/; print if $p && /M[SD]T/';
            $f = popen('cat $(ls ~mdriscoll/spurge/arc_report_* | tail -n 1) | '
                . ' perl -ne \''.$perl.'\' | '
                . ' awk \'{print $1}\' | sort -n | uniq', "r");

            while ($l = rtrim(fgets($f))) {
                $extra = (in_array($l, $known_multis) ? '&multi=1' : '');
                echo "<li><a href=\"$selfurl?customerid=$l$extra\">$l</a>";
                if ($extra) { echo ' <sup>**</sup>'; }
                echo "</li>\n";
            }
        ?>
        </ul>
        <p class="help-block">** indicates a known multi-worker customer.</p>
    </div>
</div>
</div>
</body>
</html>
