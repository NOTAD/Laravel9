<html>
   <head>
      <title>Contact us - Allaravel.com Example</title>
      <link href = "https://fonts.googleapis.com/css?family=Arial:100" rel = "stylesheet" type = "text/css">
      <style>
         html, body {
            height: 100%;
         }
         body {
            margin: 0;
            padding: 0;
            width: 100%;
            display: table;
            font-weight: 100;
            font-family: 'Arial';
         }
         .container {
            text-align: center;
            display: table-cell;
            vertical-align: middle;
         }
         .content {
            text-align: center;
            display: inline-block;
         }
         .title {
            font-size: 96px;
         }
      </style>
   </head>
   <body>
      <div class = "container">
         <div class = "content">
            <form action = "/contact" method = "post">
               <input type = "hidden" name = "_token" value = "<?php echo csrf_token() ?>">
               <table>
                  <tr>
                     <td>Họ và tên</td>
                     <td><input type = "text" name = "name" /></td>
                  </tr>

                  <tr>
                     <td>Tiêu đề</td>
                     <td><input type = "text" name = "title" /></td>
                  </tr>

                  <tr>
                     <td>Nội dung</td>
                     <td>
                        <textarea name="message" rows="5"></textarea>
                     </td>
                  </tr>

                  <tr>
                     <td colspan = "2" align = "center">
                        <input type = "submit" value = "Gửi" />
                     </td>
                  </tr>
               </table>
            </form>
         </div>
      </div>
   </body>
</html>