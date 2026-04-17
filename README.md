*  * CSV Column Extractor
*
* Professional PHP script to extract specific column values from CSV files
*
* 
* Usage: php csvxtract.php --find=columnName [--file=path/to/file.csv] [--output=json|table|csv] [--save=output.txt] [--unique]
*
* Examples:
*   php csvxtract.php --find=spTelephone
*   php csvxtract.php --find=email --file=data.csv --output=json
*   php csvxtract.php --find=fname --output=table --unique
*   php csvxtract.php --find=spTelephone --save=results.txt
    */