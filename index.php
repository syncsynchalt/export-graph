<?php

$selfurl = filter_input(INPUT_SERVER, 'PHP_SELF', FILTER_SANITIZE_URL);

if (@$_REQUEST['customerid']) {

    $cid = (int)$_REQUEST['customerid'];

    $plotfile = "/tmp/exp-gnuplot-".getmypid().".gnuplot";
    $csvfile = "/tmp/exp-input-data.".getmypid().".csv";
    $output = "/tmp/exp-output-".getmypid().".svg";
    $errlog = "/tmp/exp-errlog-".getmypid().".txt";

    $match='.*_(\\d{4})(\\d\\d)(\\d\\d)-(\\d\\d)(\\d\\d)-.*'.$cid.'[^\\d]+([0-9.]+).*';
    $repl='$1$2$3$4$5,$6';
    system("egrep -B 1000 'Workers ordered by start date' -r ~mdriscoll/spurge "
        // these look like customer percentages
        . " | egrep '$cid.*(MST|MDT)' "
        . " | grep -v '.*|.*|.*|.*|' "
        // trim down to our CSV
        . " | perl -ne 's/$match/$repl/ and print' "
        // convert times to epoch
        . " | perl -MTime::Local -pe 's/^(\d{4})(\d\d)(\d\d)(\d\d)(\d\d)/timelocal(0,\$5,\$4,\$3,\$2-1,$1)/e' "
        // put an "x" to break up large gaps
        . " | sort | uniq "
        . " | perl -pE '(\$n)=(m/(.*),/); if (\$d && \$n > \$d+43200) {say \$d-43200,\",x\"} \$d=\$n' "
        . " > $csvfile 2>> $errlog");
    $min=chop(`cat $csvfile | cut -f1 -d, | sort -n  | head -n 1`);
    $max=chop(`cat $csvfile | cut -f1 -d, | sort -nr | head -n 1`);
    $maxval=chop(`cat $csvfile | tail -n1 | cut -f2 -d,`);

    $multi_check = `cat $csvfile | cut -f1 -d, | sort | uniq -c | sort -nr | head -n1 | awk '{print \$1}'`;
    if ($multi_check > 1) {
        $graphtype = 'dots';
    } else {
        $graphtype = 'lines';
    }

    $extra = '';
    if ($max > $min && ($max - $min) < 10*24*60*60) {
        $extra = "epoch_offset=946684800\n";
        $extra .= "x1=($min-epoch_offset)-($min%86400)\n";
        $extra .= "set xtics x1,86400\n";
    }

    $fmt = '%.0f%%';
    if ($maxval < 10) {
        $fmt = '%.1f%%';
    }

    $pf = fopen($plotfile, "w");
    $plotcmds = <<<EOT
        set datafile separator ","
        set terminal svg size 800,600
        set title font ",18"
        set title "Customer $cid Export Progress"
        set xdata time
        set timefmt "%s"
        set format x "%m/%d"
        set format y '$fmt'
        set datafile missing 'x'
        $extra
        set yrange [0:]
        set key off
        set grid
        plot "$csvfile" using 1:(\$2) with $graphtype lw 2 lt 2
EOT;
    fwrite($pf, $plotcmds);
    fclose($pf);

    header("Content-Type: text/html");
    if (filesize($csvfile) === 0) {
        echo "Customer $cid not found\n";
    } else {
        ?>
            <html>
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=800">
            <title>Customer <?=$cid?> Export Progress</title>
            </head>
            <body>
        <?php
        system("gnuplot < $plotfile > $output 2>> $errlog");
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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="format-detection" content="telephone=no">
<style>
.inset {
    padding-left: 3em;
}
.helptip {
    cursor: default;
}
</style>
</head>
<title>Export Progress Grapher</title>
<body>
<div class="container-fluid">
<div class="group">
    <h2>Graph a customer's export progress</h2>
    <div class="inset">
        <form method="GET" action="<?= $selfurl; ?>" style="max-width: 250px">
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
    <h3>List of active customer ids</h3>
    <div class="inset">
        <p>Sorted by longest-running first.</p>

        <h4>US customers <small>(<span id="us-count"></span>)</small></h4>
        <ul id="us-list" class="list-unstyled">
        <?php
            $fragilefile = "/tmp/exp-fragiles-".getmypid().".txt";
            system('cat $(ls ~mdriscoll/spurge/arc_report_* | tail -n 1) | '
                . ' sed -ne \'/Fragile.customers/,/^$/ p\' > '.$fragilefile);

            $tmpfile = "/tmp/exp-tmpfile-".getmypid().".txt";
            system('cat $(ls ~mdriscoll/spurge/arc_report_* | tail -n 1) | '
                . ' sed -ne \'/Workers ordered by start date/,/rows)/ p\' | '
                . ' grep M[SD]T | awk \'{print $1}\' > ' . $tmpfile);

            $f = popen("cat $tmpfile | awk '!x[\$0]++'", "r");

            while ($l = rtrim(fgets($f))) {
                echo "<li><a href=\"$selfurl?customerid=$l\">$l</a>";
                if (`grep $l $fragilefile` != '')
                    echo " <sup class='helptip fragile'>fragile</sup> ";
                if ((int)`grep -c $l $tmpfile` > 1)
                    echo " <sup class='helptip multi'>multi</sup> ";
                echo "</li>\n";
            }
            unlink($tmpfile);
        ?>
        </ul>
        <script>
            document.getElementById('us-count').innerHTML = document.querySelectorAll('#us-list li').length;
        </script>

        <h4>International customers <small>(<span id="intl-count"></span>)</small></h4>
        <ul id="intl-list" class="list-unstyled">
        <?php
            $tmpfile = "/tmp/exp-tmpfile2-".getmypid().".txt";
            $perl = '$p=1 if /Workers ordered by start date/;
                     $p=2 if ($p && /internationals .if any/);
                     $p=0 if ($p==2 && /^[^ ]/);
                     print if ($p==2 && /M[SD]T/)';
            system('cat $(ls ~mdriscoll/spurge/arc_report_* | tail -n 1) | '
                . ' perl -ne \''.$perl.'\' | awk \'{print $1}\''
                . " > $tmpfile");
            $f = popen("cat $tmpfile | awk '!x[\$0]++'", "r");

            while ($l = rtrim(fgets($f))) {
                echo "<li><a href=\"$selfurl?customerid=$l\">$l</a>";
                if (`grep $l $fragilefile` != '')
                    echo " <sup class='helptip fragile'>fragile</sup> ";
                if ((int)`grep -c $l $tmpfile` > 1)
                    echo " <sup class='helptip multi'>multi</sup> ";
                echo "</li>\n";
            }
            unlink($tmpfile);
            unlink($fragilefile);
        ?>
        </ul>
        <script>
            document.getElementById('intl-count').innerHTML = document.querySelectorAll('#intl-list li').length;
        </script>
        <script>
            [].forEach.call(document.querySelectorAll('.multi'), function (el) {
                el.setAttribute('title', 'multiple workers were detected');
            });
            [].forEach.call(document.querySelectorAll('.fragile'), function (el) {
                el.setAttribute('title', 'running >7 days with no downloads');
            });
        </script>
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
