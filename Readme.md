# Adaptive question behaviour for multi-part questions.

This Moodle question behaviour was created by Tim Hunt of the Open University.

It is like the standard adaptive behaviour, but for questoins that are considered
to be made up of a number of separate parts. Each part of the question can register
a try at different times (whenever its inputs are complete, valid and have changed
since the last try). This question behaviour was created for use with STACK
https://github.com/sangwinc/moodle-qtype_stack/

To install using git, type this command in the root of your Moodle install
    git clone git://github.com/timhunt/moodle-qbehaviour_adaptivemultipart.git question/behaviour/adaptivemultipart
    echo question/behaviour/adaptivemultipart >> .git/info/exclude

Then download the zip from
    https://github.com/timhunt/moodle-qbehaviour_adaptivemultipart/zipball/master
unzip it into the question/behaviour folder, and rename the new
folder to adaptivemultipart.
