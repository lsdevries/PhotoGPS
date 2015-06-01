# PhotoGPS
A small and simple script to merge GPS data from pictures to other pictures without GPS data, shot at a nearby time. 
It was meant for personal use during some project I was doing abroad, but feel free to use it. For example, if you're
shooting pictures with a camera phone with GPS module and a descent camera without and you would like to store the location
data in your raw pictures.

Requirements
------------
[ExifTool](http://www.sno.phy.queensu.ca/~phil/exiftool/install.html) by Phil Harvey
and Php commandline available

Installation
------------
```bash
# clone script to a (public available) directory:
git clone https://github.com/lsdevries/PhotoGPS ./

# you might want to create an alias like so:
alias photogps="php /path/to/PhotoGPS.php"
```

Run
---
To merge photos using the alias create before, run:
```bash
photogps [OPTIONS] <PATH>
photogps /path/to/your/pictures
photogps -r /path/to/your/pictures # to run script also in sub dirs
```

Options
-------
 Option           | GNU long option	  | Meaning
 -----------------|-------------------|----------
 -h               |--help             |Show help message
 -r               |--recursive        |Also process all subfolders
 -d               |--delete_originals |Delete originals (created when processing)
