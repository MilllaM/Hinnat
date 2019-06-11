<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    <title>Document</title>
    <style type="text/css">
        body {
        font-family: 'Roboto', sans-serif;
    }
        .center {
            display: block;
            margin-left: auto;
            margin-right: auto;
            margin-top: 40px;
            width: 50%;
        }
        table {
            width: 100%;
        }
        th {
            text-align: center;
            background-color: #ECF0F1;
            color: black;
        }
        tr {
            text-align: right;
        }
        tbody tr:nth-child(odd) {
            background-color: #BDC3C7;
        }
        tbody tr:nth-child(even) {
            background-color: #ECF0F1;
        }
        h1, h3 {
            text-align: center;
            color: #4298DB;
        } 
        .submitButton {
            background-color:#4298DB;
            -moz-border-radius:6px;
            -webkit-border-radius:6px;
            border-radius:6px;
            display:inline-block;
            cursor:pointer;
            color:#ffffff;
            font-size:17px;
            font-weight:bold;
            padding:12px 44px;
            text-decoration:none;
            text-shadow:0px 1px 0px #528ecc;
        }
        .homeButton {            
            background-color:#BDC3C7;
            -moz-border-radius:6px;
            -webkit-border-radius:6px;
            border-radius:6px;
            display:inline-block;
            margin-left: 40%;
            cursor:pointer;
            color:#ffffff;
            font-size:17px;
            
            padding:12px 44px;
            text-decoration:none;
            
        }      
       
    </style>

    <script type="text/javascript">
    function GetSelected() {
        //Reference the table
        var grid = document.getElementById("results");
 
        //Reference the CheckBoxes in table
        var checkBoxes = grid.getElementsByTagName("INPUT");
        var message = [];

        //Loop through the checkboxes.
        for (var i = 0; i < checkBoxes.length; i++) {
            if (checkBoxes[i].checked) {
                var row = checkBoxes[i].parentNode.parentNode;
                
                var itemtobeupdated = [
                    row.cells[9].innerText,
                row.cells[4].innerText
                ];
                message.push(itemtobeupdated);
            }
        }
        document.selections.selected_items.value = message;
        document.getElementById("selections").submit();
        
    }

    function CheckUncheckAll(chkAll) {
        //fetch all rows
        var rows = document.getElementById("results").rows;
         //loop through all rows, excl.the header
        for (var i=1; i<rows.length; i++) {
            rows[i].getElementsByTagName("INPUT")[0].checked = chkAll.checked;
        } 
    }

    function CheckUncheckHeader() {
        //reference the checkbox in header row
        var chkAll = document.getElementById("chkAll");

        chkAll.checked = true;  //by default set to checked
        var rows = document.getElementById("results").rows;

        //loop through all rows, excl.the header
        for (var i=1; i<rows.length; i++) {
            if(!rows[i].getElementsByTagName("INPUT")[0].checked) {
                chkAll.checked = false;
                break;
            }
        }
    }

    </script>
</head>
<body>
<img src="http://path/path/logo.png" alt="logo" class="center">
<p align="center">
    <h1>Price list updates</h1>
    <h3>Price list successfully uploaded and compared</h3>
    
</p>

<?php if (count($content)>0): ?>
<h4>Select the updates you want to make</h4> 
<table id="results" cellspacing="0" rules="all" border="1" style="border-collapse: collapse;">
    <thead>
    <tr>
        <th style="width:3%;"><input type="checkbox" id="chkAll" onclick="CheckUncheckAll(this)" /></th>
        <th style="width:10%;">NEW item ID</th>
        <th style="width:31%;">NEW item name</th>
        <th style="width:31%;">Existing item name</th>
        <th style="width:5%;">NEW price</th>
        <th style="width:5%;">Existing purchase price</th>
        <th style="width:5%;">Markup %</th>
        <th style="width:5%;">Current selling price</th>
        <th style="width:5%;">New selling price</th>
        <th style="display:none;">existingID</th>
    </tr>
    </thead>
    <tbody>
        <?php foreach ($content as $row): ?>
        <tr>
            <td><input type="checkbox" onclick="CheckUncheckHeader()" style="position: relative" /></td>
            <td><?php echo $row['NEW item ID']; ?></td>
            <td><?php echo $row['NEW item name']; ?></td>
            <td><?php echo $row['Existing item name']; ?></td>
            <td>£<?php echo $row['NEW price']; ?></td>
            <td>£<?php echo $row['Existing purchase price']; ?></td>
            <td><?php echo $row['Markup %']; ?>%</td>
            <td>£<?php echo $row['Selling price current']; ?></td>
            <td>£<?php echo $row['Selling price after change']; ?></td>
            <td style="display:none;"><?php echo $row['existingID']; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<br>
<input type="button" class="submitButton" value="Update the selected items" onclick="GetSelected()" />
<form method="post" enctype="multipart/form-data" name="selections" id="selections" action="./update">
<input type="hidden" name="selected_items">
</form>
<?php elseif (count($content)<=0): ?>
<br>
<h4 align="center">No matching items found.</h4>
<a href="/slimmapi/public/" align="center"><button class="homeButton">Back to home screen</button></a> 
<?php endif; ?>
<br>

</body>
</html>