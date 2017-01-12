<?php

$selfurl = filter_input(INPUT_SERVER, 'PHP_SELF', FILTER_SANITIZE_URL);

if (@$_REQUEST['customerid']) {

    $cid = (int)$_REQUEST['customerid'];

    $plotfile = "/tmp/exp-gnuplot-".getmypid().".gnuplot";
    $csvfile = "/tmp/exp-input-data.".getmypid().".csv";
    $output = "/tmp/exp-output-".getmypid().".svg";
    $errlog = "/tmp/exp-errlog-".getmypid().".txt";

    $match='.*_(\\d{4})(\\d\\d)(\\d\\d)-(\\d\\d)(\\d\\d)-.*'.$cid.'[^\\d]+([0-9.]+).*';
    $repl='$1-$2-$3 $4:$5:00,$6';
    system("egrep -B 1000 'Workers ordered by start date' -r ~mdriscoll/spurge "
        . " | egrep '$cid.*(MST|MDT)' "
        . " | grep -v '.*|.*|.*|.*|' "
        . " | perl -ne 's/$match/$repl/ and print' | sort | uniq > $csvfile 2>> $errlog");

    $multi_check = `cat $csvfile | cut -f1 -d, | sort | uniq -c | sort -nr | head -n1 | awk '{print \$1}'`;
    if ($multi_check > 1) {
        $graphtype = 'dots';
    } else {
        $graphtype = 'lines';
    }

    $pf = fopen($plotfile, "w");
    $plotcmds = <<<EOT
        set datafile separator ","
        set terminal svg size 800,500
        set title font ",18"
        set title "Customer $cid Export Progress"
        set xdata time
        set timefmt "%Y-%m-%d %H:%M:%S"
        set format x "%m/%d"
        set format y '%.0f%%'
        set key off
        set grid
        plot "$csvfile" using 1:2 with $graphtype lw 2 lt 2
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

    @unlink($plotfile);
    @unlink($csvfile);
    @unlink($output);
    @unlink($errlog);

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
<meta name="viewport" content="width=device-width, initial-scale=1,
                               maximum-scale=1, user-scalable=no">
<meta name="format-detection" content="telephone=no">
<style>
.inset {
    padding-left: 3em;
}
</style>
</head>
<title>Export Progress Grapher</title>
<body>
<div class="container-fluid">
<div class="group">
    <h2>Graph a customer's export progress</h2>
    <div class="inset">
        <form method="GET" action="<?= $selfurl; ?>" style="max-width: 300px">
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
            <div class="form-group">
                <button type="submit" class="btn btn-default">Submit</button>
            </div>
        </form>
    </div>
</div>
<div class="group">
    <h3>List of active US customer ids</h3>
    <div class="inset">
        <p>Sorted by longest-running first.</p>
        <ul class="list-unstyled">
        <?php
            $perl = '$p=1 if /Workers ordered by start date/; $p=0 if /rows\)/; print if $p && /M[SD]T/';
            $f = popen('cat $(ls ~mdriscoll/spurge/arc_report_* | tail -n 1) | '
                . ' perl -ne \''.$perl.'\' | '
                . ' awk \'{print $1}\' | awk \'!x[$0]++\'', "r");

            while ($l = rtrim(fgets($f))) {
                echo "<li><a href=\"$selfurl?customerid=$l\">$l</a></li>\n";
            }
        ?>
        </ul>
    </div>
</div>
<div class="group">
    <div class="inset">
    <br>
    <p>last updated <?= `ls ~mdriscoll/spurge/arc* | tail -n 1 | sed -e 's/.*arc_report_//' `; ?></p>
    </div>
</div>
</div>
</body>
</html>
