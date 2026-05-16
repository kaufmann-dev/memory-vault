<?php
    session_start();

    $texts = json_decode(file_get_contents('../../../json/writings.json'), true)['texts'];
    $users = json_decode(file_get_contents('../../../json/users.json'), true)['users'];
    $nav = file_get_contents('../../nav');

    $text = null;
    foreach($texts as $lole){
        if($lole["id"] == $_GET["id"]){
            $text = $lole;
            break;
        }
    }

    function Authenticate(){
        global $users;
        foreach($users as $lola){
            if(isset($_SESSION["username"]) && $_SESSION["username"] == $lola['username'] && $_SESSION["password"] == $lola['password']){
                return true;
            }
        }
        return false;
    }
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>The Second Directory</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="The Second Directory.">
        <link rel="stylesheet" type="text/css" href="/css/datatables.min.css"/>
        <link href="/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="/css/bootstrap-icons.min.css">
        <link href="/css/style.css" rel="stylesheet">
        <link rel="icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/favicon.ico">
    </head>
    <body>
        <?php eval("?> $nav <?php "); ?>
        <div class="container my-3">
            <?php
                if(!isset($_GET["id"])){
                    echo"Please select a text.";
                } elseif($text["access"] == "private" && !Authenticate()){
                    echo"You need to be logged in to access this text.";
                } else {
                ?>
                    <table class="table" id="textinfo">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Language</th>
                                <?php 
                                    if(!empty($text["labels"][0])){ echo "<th>Labels</th>"; }
                                    if(!empty($text["series"])){ echo"<th>Series</th>"; }
                                ?>
                                <th>Access level</th>
                                <th>Words</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr> <?php
                                echo "<td>".$text["date"]."</td>";
                                echo "<td>".$text["lang"]."</td>";
                                if(!empty($text["labels"][0])){ echo "<td>".implode(", ",$text["labels"])."</td>"; }
                                if(!empty($text["series"])){ echo "<td>".$text["series"]."</td>"; }
                                echo "<td>".$text["access"]."</td>";
                                echo "<td>".$text["words"]."</td>";
                                echo "<td>";
                                    echo"<a id='homebtn' href='/texts/' class='btn btn-sm btn-secondary me-1'><i class='bi bi-list-columns-reverse'></i> Index</a>";
                                    echo"<a id='editbtn' class='btn btn-sm btn-secondary me-1'><i class='bi bi-pencil-square'></i> Edit</a>";
                                    echo"<a id='delbtn' class='btn btn-sm btn-danger'><i class='bi bi-trash3-fill'></i> Delete</a>";
                                echo"</td>";
                            ?> </tr>
                        </tbody>
                    </table>
                    <h1 class="display-4 mt-4 mb-3"><?php echo $text["title"]; ?></h1>
                    <div class="rounded bg-dark p-1 my-3"></div>
                    <div class='textcontent'><?php
                        $bruh = explode("\n", $text["content"]);
                        foreach($bruh as $bre){
                            if(empty($bre)){
                                echo"<br>";
                            } else {
                                echo"<span>$bre</span>";
                            }
                        }
                    ?></div>
                <?php
                }
            ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script src="/js/datatables.min.js"></script>
        <script>
            $(document).ready( function () {
                $("#editbtn").attr("href", "<?php echo("./edit/?id=".$text["id"]); ?>");
                $('#textinfo').DataTable({
                    "autoWidth": false,
                    responsive: true,
                    "pageLength": 10,
                    dom: 'rt'
                });
                $("#delbtn").click(function() {
                    if(confirm("Are you sure that you want to delete this writing?")){
                        location.href="<?php echo("./delete/?id=".$text["id"]); ?>";
                    }
                });
            } );
        </script>
    </body>
</html>