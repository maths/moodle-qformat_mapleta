# MapleTA question importer

__This feature currently only has minimal functionality.__

The intention is to work towards creating an importer as individuals bring libraries of questions for import.  The current intention is not to create a fully featured importer.  In any case, this is likely to be impossible as there are mutually incompatible features between the two systems.  At best, we can import questions placing corresponding fields in the correct places pending editing by an intelligent human.  This will, at least, save significant tedious cut and paste.  If you need this functionality please contact the STACK developers for more information.

This Moodle question import format can import questions exported from a MapleTA system into Moodle with STACK 4.0 installed (https://github.com/maths/moodle-qtype_stack/). 

It was created by Chris Sangwin and further developed by George Kinnear.

To install, either [download the zip file](https://github.com/maths/moodle-qformat_mapleta/zipball/master),
unzip it, and place it in the directory `moodle\question\format\mapleta`.
(You will need to rename the directory `moodle-qformat_mapleta -> mapleta`.)
Alternatively, get the code using git by running the following command in the
top level folder of your Moodle install:

    git clone git://github.com/maths/moodle-qformat_mapleta.git question/format/mapleta


## Converting MathML to LaTeX

MapleTA exports may include MathML in question text, which is tedious to edit manually. It is possible to convert these mathematical expressions to LaTeX automatically, so that they appear in a more easily editable form in the resulting STACK questions.

Currently, the importer cannot do this entirely automatically. You must pre-process the MapleTA .xml files before importing them in Moodle.

### Setup

The pre-processing makes use of the following:

* SaxonHE (tested with verion 9.7), available from http://saxon.sourceforge.net/
* mml2tex, available from https://github.com/transpect/mml2tex
* mathml2tex batch file, provided with this package
* xmlentities.txt, provided with this package

Once SaxonHE and mml2tex are in place, you need to update the first lines of the mathml2tex batch file with the correct paths to these packages.

### Pre-processing procedure

The process is as follows:

1. Save your MapleTA .xml file in a writeable location. (You can also use a .zip file produced by a MapleTA export; the process will then run on manifest.xml from this file.)
2. Open a terminal and run the mathml2tex batch file with your file as an argument, e.g.
    bash mathml2tex /tmp/mymapleta.xml
3. The batch file will then:
  - make some modifications to the XML file, including adding the contents of xmlentities.txt, in order to make it valid XML which SaxonHE can process.
  - Use SaxonHE, with the XSLT transformations provided by mml2tex, to transform mathml to <?mml2tex ... ?>
  - Do some further tidying up, essentially undoing some of the modifications in the first step.
4. The batch file then outputs a new .xml file with "_tex" appended to the filename (e.g. "mymapleta_tex.xml") alongside the original input. This file is ready to be uploaded to the convertor in Moodle.