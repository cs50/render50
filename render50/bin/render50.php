<?php

    /**
     * @author David J. Malan <malan@harvard.edu>
     * @link https://manual.cs50.net/render50
     * @package render50
     * @version 1.7
     *
     * GNU General Public License, version 2
     * http://www.gnu.org/licenses/gpl-2.0.html
     */

    // constants
    define("VERSION", "1.8");

    // report all errors
    error_reporting(E_ALL);
    ini_set("display_errors", true);

    // GeSHi
    require_once(dirname(__FILE__) . "/../lib/geshi-1.0.8.11/geshi.php");
     
    // mPDF
    require_once(dirname(__FILE__) . "/../lib/mpdf-5.7.1/mpdf.php");

    // override mPDF's error handling
    class _mPDF extends mPDF
    {
        function Error($msg)
        {
            die("Error: $msg\n");
        }
    }

    // override some of GeShHi's assumptions of file extensions
    $LOOKUP = array(
     "objc" => array("h", "m", "mm", "pch")
    );

    // explain usage
    if (in_array("-h", $argv) || in_array("--help", $argv))
    {
        echo "Usage: render50 output inputs [-i includes] [-x excludes]\n\n";
        echo "  output\n";
        echo "     name of the PDF to be outputted; if output itself does not end in .pdf,\n";
        echo "     the extension will be automatically appended\n\n";
        echo "  inputs\n";
        echo "     space-separated list of files and/or directories to be rendered (unless\n";
        echo "     implicitly excluded by -i or implicitly excluded by -x); if inputs is\n";
        echo "     just --, the list of files and/or directories will be read from standard\n";
        echo "     input, one per line\n\n";
        echo "  -i includes\n";
        echo "     space-separated list of files to be included in rendering to the exclusion\n";
        echo "     of files otherwise implied by inputs, as in\n";
        echo "     `php /path/to/render.php output.pdf directory -i *.php`\n\n";
        echo "  -x excludes\n";
        echo "     space-separated list of files to be excluded from rendering, even if\n";
        echo "     otherwise implied by inputs, as in\n";
        echo "     `php /path/to/render.php output.pdf directory -x *.css *.js`\n\n";
        exit(1);
    }
    else if (in_array("-v", $argv) || in_array("--version", $argv))
    {
        echo VERSION . "\n";
        exit(1);
    }

    // ensure proper usage
    if ($argc < 3)
    {
        echo "Usage: render50 output inputs [-i includes] [-x excludes]\n";
        exit(1);
    }

    // filename for output
    $output = preg_match("/\.pdf$/i", $argv[1]) ? $argv[1] : $argv[1] . ".pdf";

    // prompt whether to create directory
    if (!file_exists($dirname = dirname($output)))
    {
        echo "create {$dirname}? (y/n [n]) ";
        if (!preg_match("/^y|Y/", fgets(STDIN)))
        {
            echo "not created\n";
            exit(1);
        }
        else
        {
            mkdir($dirname, 0777, true);
        }
    }

    // prompt whether to overwrite file
    if (file_exists($output))
    {
        echo "overwrite $output? (y/n [n]) ";
        if (!preg_match("/^y|Y/", fgets(STDIN)))
        {
            echo "not overwritten\n";
            exit(1);
        }
    }

    // check for includes
    for ($i = 3; $i < $argc; $i++)
    {
        if ($argv[$i] == "-i")
            $includes = array();
        else if ($argv[$i][0] == "-" && isset($includes))
            break;
        else if (isset($includes))
            array_push($includes, str_replace("*", ".*", preg_replace("/({|}|\||\.)/", '\\\$1', $argv[$i])));
    }
    if (isset($includes)) 
        $includes = "{^" . join("|", $includes) . "$}";

    // check for excludes
    for ($i = 3; $i < $argc; $i++)
    {
        if ($argv[$i] == "-x")
            $excludes = array();
        else if ($argv[$i][0] == "-" && isset($excludes))
            break;
        else if (isset($excludes))
            array_push($excludes, str_replace("*", ".*", preg_replace("/({|}|\||\.)/", '\\\$1', $argv[$i])));
    }
    if (isset($excludes)) 
        $excludes = "{^" . join("|", $excludes) . "$}";

    // check STDIN else command line for inputs
    if ($argv[2] == "--")
    {
        echo "Taking inputs from STDIN, one per line...  Hit Ctrl-D when done else Ctrl-C to cancel.\n";
        $patterns = file("php://stdin", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    else
    {
        $patterns = array();
        for ($i = 2; $i < $argc; $i++)
        {
            if ($argv[$i][0] == "-")
                break;
            array_push($patterns, $argv[$i]);
        }
    }

    // glob patterns lest shell (e.g., Windows) not have done so
    $inputs = array();
    foreach ($patterns as $pattern)
    {
        // search for paths that match pattern
        $paths = glob($pattern, GLOB_BRACE);

        // sort paths that match pattern
        natsort($paths);
        $inputs = array_merge($inputs, $paths);
    }

    // queue of files to render
    $queue = array();

    // parse command line for files and directories
    foreach ($inputs as $input)
    {
        // ensure input is readable
        if (!is_readable($input))
            die("Cannot read: {$input}\n");

        // if input is file, add to queue
        if (is_file($input))
            array_push($queue, $input);

        // else if input is directory, add descendants to queue
        else if (is_dir($input))
        {
            // input's files
            $files = array();

            // directories into which to descend
            $directories = array($input);

            // descend into directories
            while (count($directories) > 0)
            {
                // pop directory
                $directory = array_shift($directories);

                // iterate over directory's children
                foreach (scandir($directory) as $child)
                {
                    // ignore . and ..
                    if ($child == "." || $child == "..")
                        continue;
            
                    // prepare child's path
                    $path = rtrim($directory, "/") . DIRECTORY_SEPARATOR . $child;

                    // ensure path is readable
                    if (!is_readable($path))
                        die("Cannot read: {$path}\n");

                    // push child's path onto array
                    if (is_dir($path))
                        array_push($directories, $path);
                    else
                        array_push($files, $path);
                }
            }

            // sort files
            natcasesort($files);

            // add files to queue
            $queue = array_merge($queue, $files);
        }
    }

    // create PDF
    $mpdf = new _mPDF("c", "Letter-L", 8);
    $mpdf->simpleTables = true;

    // create highlighter
    $geshi = new GeSHi();
    $geshi->enable_keyword_links(false);
    $geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
    $geshi->set_header_type(GESHI_HEADER_DIV);
    $geshi->set_tab_width(4);
    
    // prepare to count pages
    $pages = 0;

    // iterate over files in queue
    foreach ($queue as $file)
    {
        // skip implicit exclusions
        if (isset($includes) && !preg_match($includes, $file))
            continue;

        // skip explicit exclusions
        if (isset($excludes) && preg_match($excludes, $file))
            continue;

        // skip dotfiles
        if (substr(basename($file), 0, 1) == ".")
            continue;

        // infer language
        if (($language = $geshi->get_language_name_from_extension(pathinfo($file, PATHINFO_EXTENSION), $LOOKUP)) == "text")
            $language = $geshi->get_language_name_from_extension(pathinfo($file, PATHINFO_EXTENSION));

        // ignore unknown languages
        if ($language == "")
        {
            // whitelist Makefile, README, and *.txt
            if (!preg_match("/^(?:Makefile|README|.*\.txt)$/i", pathinfo($file, PATHINFO_BASENAME)))
                continue;
        }

        // encode source; trim trailing newline, if any
        $source = utf8_encode(preg_replace("/\r?\n$/", "", file_get_contents($file)));

        // ignore binary files
        if (strpos($source, "\x00") !== false)
            continue;

        // report progress
        echo "Rendering $file...\n";

        // set source
        $geshi->set_source($source);

        // set language
        $geshi->set_language($language, true);

        // set header
        $properties = array(
         "border-bottom: 1px solid #808080",
         "color: #808080",
         "font-family: monospace",
         "text-align: right"
        );
        $mpdf->SetHTMLHeader("<div style='" . join(";", $properties) . "'>" . htmlspecialchars($file) . "</div>");

        // add file to PDF
        $mpdf->AddPage();
        $mpdf->Bookmark($file);
        $mpdf->WriteHTML($geshi->parse_code());
        $pages++;
    }

    // count pages
    if ($pages == 0)
    {
        echo "Nothing to render.\n";
        exit(1);
    }

    // output PDF
    @$mpdf->Output($output, "F");
    echo "PDF saved as $output.\n";
    exit(0);

?>
