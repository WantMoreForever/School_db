<?php
// 测试PHP是否正常运行
echo "教务系统环境搭建成功！";

// 测试MySQL数据库连接
$host = 'localhost'; // 数据库地址（本地固定）
$user = 'root';      // 数据库账号
$pwd = 'yaoxicheng';       // 数据库密码
$dbname = 'school_db'; // 刚才建的数据库名

// 创建连接
$conn = mysqli_connect($host, $user, $pwd, $dbname);

// 判断连接是否成功
if($conn){
    echo "<br>数据库连接成功！可以开始开发教务系统了";
}else{
    echo "<br>数据库连接失败：".mysqli_connect_error();
}
?>