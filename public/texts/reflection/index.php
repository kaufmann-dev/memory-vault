<?php
    session_start();
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
        <?php
            $reflections = json_decode(file_get_contents('../../../json/reflections.json'), true)['reflections'];
            $questions = json_decode(file_get_contents('../../../json/reflections.json'), true)['questions'];
            $users = json_decode(file_get_contents('../../../json/users.json'), true)['users'];
            $nav = file_get_contents('../../nav');

            $reflection = null;
            foreach($reflections as $lole){
                if($lole["id"] == $_GET["id"]){
                    $reflection = $lole;
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
            
            eval("?> $nav <?php ");
        ?>
        <div class="container my-3">
            <?php
                if(!isset($_GET["id"])){
                    echo"Please select a reflection.";
                } elseif($reflection["access"] == "private" && !Authenticate()){
                    echo"You need to be logged in to access this reflection.";
                } else { ?>
                    <table class="table" id="textinfo">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Language</th>
                                <th>Access level</th>
                                <th>Words</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr> <?php
                                echo "<td>".$reflection["date"]."</td>";
                                echo "<td>".$reflection["lang"]."</td>";
                                echo "<td>".$reflection["access"]."</td>";
                                echo "<td>".$reflection["words"]."</td>";
                                echo "<td>";
                                    echo"<a id='homebtn' href='/texts/' class='btn btn-sm btn-secondary me-1'><i class='bi bi-list-columns-reverse'></i> Index</a>";
                                    echo"<a id='editbtn' class='btn btn-sm btn-secondary me-1'><i class='bi bi-pencil-square'></i> Edit</a>";
                                    echo"<a id='delbtn' class='btn btn-sm btn-danger'><i class='bi bi-trash3-fill'></i> Delete</a>";
                                echo"</td>";
                            ?> </tr>
                        </tbody>
                    </table> 
                    <?php echo '<h1 class="display-4 mt-4 mb-3">' . $reflection["title"] . '</h1>'; ?>
                    <div class="rounded bg-dark p-1 my-3"></div>
                    <div class='row'>
                        <div class="col col-12 col-md-8 mb-4"> <?php
                            foreach($reflection["questions"] as $question => $answer){
                                echo"<div class='mb-4'>";
                                foreach($questions as $q){
                                    if($q["shortcut"] == $question){
                                        echo "<h5>" . $q["question"] . "</h5>";
                                        break;
                                    }
                                }
                                foreach(explode("\n", $answer) as $content){
                                    if(empty($content)){
                                        echo"<br>";
                                    } else {
                                        echo"<span class='d-block'>$content</span>";
                                    }
                                }
                                echo"</div>";
                            } ?>
                        </div>
                        <div class="col col-12 col-md-4">
                            <?php
                            foreach($reflection["images"] as $imgsrc){
                                echo"<img class='w-100 rounded mb-3' alt='Photo of me' src='data: ".mime_content_type("../../../img/reflections/".$imgsrc).";base64,".base64_encode(file_get_contents("../../../img/reflections/".$imgsrc))."'>";
                            }
                            ?>
                        </div>
                    </div>
                <?php } ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script src="/js/datatables.min.js"></script>
        <script>
            $(document).ready( function () {
                $("#editbtn").attr("href", "<?php echo("./edit/?id=".$reflection["id"]); ?>");
                $('#textinfo').DataTable({
                    "autoWidth": false,
                    responsive: true,
                    "pageLength": 10,
                    dom: 'rt'
                });
                $("#delbtn").click(function() {
                    if(confirm("Are you sure that you want to delete this reflection?")){
                        location.href="<?php echo("./delete/?id=".$reflection["id"]); ?>";
                    }
                });
            } );
        </script>
    </body>
</html>