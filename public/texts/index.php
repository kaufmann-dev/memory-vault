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
        <link href="/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" type="text/css" href="/css/datatables.min.css"/>
        <link rel="stylesheet" href="/css/bootstrap-icons.min.css">
        <link href="/css/style.css" rel="stylesheet">
        <link rel="icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/favicon.ico">
    </head>
    <body>
        <?php
            $texts = json_decode(file_get_contents('../../json/writings.json'), true)['texts'];
            $reflections = json_decode(file_get_contents('../../json/reflections.json'), true)['reflections'];
            $pdfs = json_decode(file_get_contents('../../json/pdfs.json'), true)['pdfs'];
            $collections = json_decode(file_get_contents('../../json/collections.json'), true)['collections'];
            $users = json_decode(file_get_contents('../../json/users.json'), true)['users'];
            $nav = file_get_contents('../nav');

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
            <nav class="mb-3">
                <div class="nav nav-pills" id="nav-tab" role="tablist">
                    <button class="nav-link" id="nav-writings-tab" data-bs-toggle="tab" data-bs-target="#nav-writings" type="button" role="tab" aria-controls="nav-writings" aria-selected="true">Writings</button>
                    <button class="nav-link" id="nav-reflections-tab" data-bs-toggle="tab" data-bs-target="#nav-reflections" type="button" role="tab" aria-controls="nav-reflections" aria-selected="false">Reflections</button>
                    <button class="nav-link" id="nav-collections-tab" data-bs-toggle="tab" data-bs-target="#nav-collections" type="button" role="tab" aria-controls="nav-collections" aria-selected="false">Collections</button>
                    <button disabled class="nav-link" id="nav-pdfs-tab" data-bs-toggle="tab" data-bs-target="#nav-pdfs" type="button" role="tab" aria-controls="nav-pdfs" aria-selected="false">PDFs</button>
                </div>
            </nav>
            <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade" id="nav-writings" role="tabpanel" aria-labelledby="nav-writings-tab" tabindex="0">
                    <h1 class="display-4">Writings</h1>
                    <p class="text-black-50">Texts of various length which I have written over the years</p>
                    <div class="rounded bg-dark p-1 my-3"></div>
                    <table class="table table-striped" id="texts">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Language</th>
                                <th>labels</th>
                                <th>Series</th>
                                <th>Access level</th>
                                <th>Words</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            $textwords = 0;
                            foreach($texts as $text){
                                if(Authenticate() || $text["access"] == "public"){
                                    echo"<tr class='text-break pointycursor'>";
                                        echo"<td class='id' >".$text["id"]."</td>";
                                        echo"<td>".$text["title"]."</td>";
                                        echo"<td>".DateTime::createFromFormat('Y-m-d', $text["date"])->format('d/m/y')."</td>";
                                        echo"<td>".$text["lang"]."</td>";
                                        echo"<td>".implode(", ",$text["labels"])."</td>";
                                        echo"<td>".$text["series"]."</td>";
                                        echo"<td>".$text["access"]."</td>";
                                        echo"<td>".$text["words"]."</td>";
                                    echo"</tr>";
                                    $textwords += $text["words"];
                                }
                            }
                            echo"</tbody></table>";
                        ?>
                </div>
                <div class="tab-pane fade" id="nav-reflections" role="tabpanel" aria-labelledby="nav-reflections-tab" tabindex="0">
                    <h1 class="display-4">Reflections</h1>
                    <p class="text-black-50">Reflecting on the past to move better forward into the future</p>
                    <div class="rounded bg-dark p-1 my-3"></div>
                    <table class="table table-striped" id="reflections">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Language</th>
                                <th>Access level</th>
                                <th>Words</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            $reflectionwords = 0;
                            foreach($reflections as $reflection){
                                if(Authenticate() || $reflection["access"] == "public"){
                                    echo"<tr class='text-break pointycursor'>";
                                        echo"<td class='id'>".$reflection["id"]."</td>";
                                        echo"<td>".$reflection["title"]."</td>";
                                        echo"<td>".DateTime::createFromFormat('Y-m-d', $reflection["date"])->format('d/m/y')."</td>";
                                        echo"<td>".$reflection["lang"]."</td>";
                                        echo"<td>".$reflection["access"]."</td>";
                                        echo"<td>".$reflection["words"]."</td>";
                                    echo"</tr>";
                                    $reflectionwords += $reflection["words"];
                                }
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade" id="nav-pdfs" role="tabpanel" aria-labelledby="nav-pdfs-tab" tabindex="0">
                <h1 class="display-4">PDFs</h1>
                    <p class="text-black-50">Styled documents stored in the PDF instead of plain text</p>
                    <div class="rounded bg-dark p-1 my-3"></div>
                    <table class="table table-striped" id="pdfs">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Language</th>
                                <th>Access level</th>
                                <th>Words</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            $pdfwords = 0;
                            foreach($pdfs as $pdf){
                                if(Authenticate() || $pdf["access"] == "public"){
                                    echo"<tr class='text-break pointycursor'>";
                                        echo"<td class='id'>".$pdf["id"]."</td>";
                                        echo"<td>".$pdf["title"]."</td>";
                                        echo"<td>".DateTime::createFromFormat('Y-m-d', $pdf["date"])->format('d/m/y')."</td>";
                                        echo"<td>".$pdf["lang"]."</td>";
                                        echo"<td>".$pdf["access"]."</td>";
                                        echo"<td>".$pdf["words"]."</td>";
                                    echo"</tr>";
                                    $pdfwords += $pdf["words"];
                                }
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade" id="nav-collections" role="tabpanel" aria-labelledby="nav-collections-tab" tabindex="0">
                    <h1 class="display-4">Collections</h1>
                    <p class="text-black-50">Coherent texts collected in singular documents</p>
                    <div class="rounded bg-dark p-1 my-3"></div>
                    <table class="table table-striped" id="collections">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Start date</th>
                                <th>End date</th>
                                <th>Language</th>
                                <th>Access level</th>
                                <th>Words</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            $collectionwords = 0;
                            foreach($collections as $collection){
                                if(Authenticate() || $collection["access"] == "public"){
                                    echo"<tr class='text-break pointycursor'>";
                                        echo"<td class='id'>".$collection["id"]."</td>";
                                        echo"<td>".$collection["title"]."</td>";
                                        echo"<td>".DateTime::createFromFormat('Y-m-d', $collection["start-date"])->format('d/m/y')."</td>";
                                        echo"<td>".DateTime::createFromFormat('Y-m-d', $collection["end-date"])->format('d/m/y')."</td>";
                                        echo"<td>".$collection["lang"]."</td>";
                                        echo"<td>".$collection["access"]."</td>";
                                        echo"<td>".$collection["words"]."</td>";
                                    echo"</tr>";
                                    $collectionwords += $collection["words"];
                                }
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>

        <script src="/js/datatables.min.js"></script>

        <script src="/js/moment.min.js"></script>
        <script src="/js/datetime-moment.min.js"></script>
        
        <script>
            $(document).ready( function () {
                if(localStorage.getItem('activeTabTexts') === null || localStorage.getItem('activeTabTexts') === "undefined"){
                    localStorage.setItem('activeTabTexts', $('div#nav-tab button').first().data('bs-target'));
                }
                $('div#nav-tab button').click(function(){
                    localStorage.setItem('activeTabTexts', $(this).data('bs-target'));
                });
                $('#nav-tabContent ' + localStorage.getItem('activeTabTexts')).addClass('show active');
                $('#nav-tab ' + localStorage.getItem('activeTabTexts') + '-tab').addClass('active');

                $.fn.dataTable.moment('DD/MM/YY');

                function CreateWritingsTable(){
                    $('#texts').DataTable({
                        "pagingType": "numbers",
                        "autoWidth": false,
                        responsive: true,
                        order: [[2, 'desc']],
                        "pageLength": 10,
                        select: {
                            style: 'single'
                        },
                        dom: 'Blfrtip',
                        buttons: [
                            {
                                text: '<i class="bi bi-box-arrow-up-right"></i> Open',
                                action: function () {
                                    location.href='./writing/?id='+this.rows({selected: true}).data()[0][0];
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '<i class="bi bi-plus-circle" title="New"></i> New',
                                action: function () {
                                    location.href='./writing/create/';
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-pencil-square" title="Edit"></i> Edit',
                                action: function () {
                                    location.href='./writing/edit/?id='+this.rows({selected: true}).data()[0][0];
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-trash3-fill" title="Delete"></i> Delete',
                                action: function () {
                                    if(confirm("Are you sure that you want to delete this text?")){
                                        location.href="./writing/delete/?id="+this.rows({selected: true}).data()[0][0];
                                    }
                                },
                                className: 'btn-danger btn-sm'
                            }
                        ],
                        "infoCallback": function( settings, start, end, max, total, pre ) {
                            return "Showing "+start+" to "+end+" of "+total+" entries<br>"+"<?php echo $textwords." words in total"; ?><br>";
                        }
                    });
                }

                function CreateCollectionsTable(){
                    $('#collections').DataTable({
                        "pagingType": "numbers",
                        "autoWidth": false,
                        responsive: true,
                        order: [[3, 'desc']],
                        "pageLength": 10,
                        select: {
                            style: 'single'
                        },
                        dom: 'Blfrtip',
                        buttons: [
                            {
                                text: '<i class="bi bi-box-arrow-up-right"></i> Open',
                                action: function () {
                                    location.href='./collection?id='+this.rows({selected: true}).data()[0][0];
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '<i class="bi bi-plus-circle" title="New"></i> New',
                                action: function () {
                                    location.href='./collection/create/';
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-pencil-square" title="Edit"></i> Edit',
                                action: function () {
                                    location.href='./collection/edit/?id='+this.rows({selected: true}).data()[0][0];
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-trash3-fill" title="Delete"></i> Delete',
                                action: function () {
                                    if(confirm("Are you sure that you want to delete this collection?")){
                                        location.href="./collection/delete/?id="+this.rows({selected: true}).data()[0][0];
                                    }
                                },
                                className: 'btn-danger btn-sm'
                            }
                        ],
                        "infoCallback": function( settings, start, end, max, total, pre ) {
                            return "Showing "+start+" to "+end+" of "+total+" entries<br>"+"<?php echo $collectionwords." words in total"; ?><br>";
                        }
                    });
                }

                function CreateReflectionsTable(){
                    $('#reflections').DataTable({
                        "pagingType": "numbers",
                        "autoWidth": false,
                        responsive: true,
                        order: [[2, 'desc']],
                        "pageLength": 10,
                        select: {
                            style: 'single'
                        },
                        dom: 'Blfrtip',
                        buttons: [
                            {
                                text: '<i class="bi bi-box-arrow-up-right"></i> Open',
                                action: function () {
                                    location.href='./reflection/?id='+this.rows({selected: true}).data()[0][0];
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '<i class="bi bi-plus-circle" title="New"></i> New',
                                action: function () {
                                    location.href='./reflection/create/';
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-pencil-square" title="Edit"></i> Edit',
                                action: function () {
                                    location.href='./reflection/edit/?id='+this.rows({selected: true}).data()[0][0];
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-trash3-fill" title="Delete"></i> Delete',
                                action: function () {
                                    if(confirm("Are you sure that you want to delete this reflection?")){
                                        location.href="./reflection/delete/?id="+this.rows({selected: true}).data()[0][0];
                                    }
                                },
                                className: 'btn-danger btn-sm'
                            }
                        ],
                        "infoCallback": function( settings, start, end, max, total, pre ) {
                            return "Showing "+start+" to "+end+" of "+total+" entries<br>"+"<?php echo $reflectionwords." words in total"; ?><br>";
                        }
                    });
                }

                function CreatePDFsTable(){
                    $('#pdfs').DataTable({
                        "pagingType": "numbers",
                        "autoWidth": false,
                        responsive: true,
                        order: [[2, 'desc']],
                        "pageLength": 10,
                        select: {
                            style: 'single'
                        },
                        dom: 'Blfrtip',
                        buttons: [
                            {
                                text: '<i class="bi bi-box-arrow-up-right"></i> Open',
                                action: function () {
                                    location.href='./pdfs?id='+this.rows({selected: true}).data()[0][0];
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '<i class="bi bi-plus-circle" title="New"></i> New',
                                action: function () {
                                    location.href='./pdfs/create/';
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-pencil-square" title="Edit"></i> Edit',
                                action: function () {
                                    location.href='./pdfs/edit/?id='+this.rows({selected: true}).data()[0][0];
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-trash3-fill" title="Delete"></i> Delete',
                                action: function () {
                                    if(confirm("Are you sure that you want to delete this PDF?")){
                                        location.href="./pdfs/delete/?id="+this.rows({selected: true}).data()[0][0];
                                    }
                                },
                                className: 'btn-danger btn-sm'
                            }
                        ],
                        "infoCallback": function( settings, start, end, max, total, pre ) {
                            return "Showing "+start+" to "+end+" of "+total+" entries<br>"+"<?php echo $pdfwords." words in total"; ?><br>";
                        }
                    });
                }

                const tabs = [
                    {
                        id: 'nav-writings-tab',
                        isActive: false,
                        createFunction: CreateWritingsTable
                    },
                    {
                        id: 'nav-collections-tab',
                        isActive: false,
                        createFunction: CreateCollectionsTable
                    },
                    {
                        id: 'nav-reflections-tab',
                        isActive: false,
                        createFunction: CreateReflectionsTable
                    },
                    {
                        id: 'nav-pdfs-tab',
                        isActive: false,
                        createFunction: CreatePDFsTable
                    }
                ];

                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        const target = mutation.target;
                        if ($(target).hasClass('active')) {
                            const tab = tabs.find(t => t.id === target.id);
                            if (tab && !tab.isActive) {
                                tab.createFunction();
                                tab.isActive = true;
                            }
                        }
                    });
                });

                tabs.forEach(tab => {
                    const element = document.querySelector(`#${tab.id}`);
                    if (element) {
                        // Check initial state on page load
                        if ($(element).hasClass('active') && !tab.isActive) {
                            tab.createFunction();
                            tab.isActive = true;
                        }
                        // Start observing for future changes
                        observer.observe(element, { attributes: true });
                    } else {
                        console.warn(`Element #${tab.id} not found`);
                    }
                });
                
                $('.dt-buttons button').each(function(){
                    if(!$(this).hasClass('btn-orig')){
                        $(this).removeClass('btn-secondary');
                    }
                });
            });
        </script>
    </body>
</html>