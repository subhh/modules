#!/bin/bash

WHITE='\033[39m';
GREEN='\033[32m';
RED='\033[31m';

function reOrder() {
    BEFORE=$1;
    AFTER=$2;
    LIST=$3;
    ITEM='';
    for I in $(seq 0 $(( ${#LIST[*]} - 1 ))); do
        if [ ${LIST[$I]} = $BEFORE ]; then
            if [ -n "$ITEM" ]; then
                LIST[$I]=$ITEM;
            fi;
            return 0;
        elif [ ${LIST[$I]} = $AFTER ]; then
            ITEM=${LIST[$I]};
            LIST[$I]=$BEFORE;
        elif [ -n "$ITEM" ]; then
            TMP=${LIST[$I]};
            LIST[$I]=$ITEM;
            ITEM=$TMP;
        fi;
    done;
}

# Zentrale Größen einlesen

TMPPATH=$(echo $(pwd) | sed -s "s/^\(.\+\)\/\([^/]\+\)$/\\1/");
echo -n "Basispfad der Anwendung (oberhalb des Anwendungsverzeichnisses) ["$TMPPATH"]: ";
read BASEPATH;
[ -z "$BASEPATH" ] && BASEPATH=$TMPPATH;

I=1;
CAT="";
echo "Installationen im Verzeichnis $BASEPATH:";
for DIR in $(ls $BASEPATH); do
    if [ "$DIR" = "${CAT}-module" ]; then
        echo $I $CAT;
        CATS[$I]=$CAT;
        (( I++ ));
    fi;
    CAT=$DIR;
done; 

APP="";
while [ -z "$APP" ]; do
    echo -n "Installation auswählen: ";
    read J;
    APP=${CATS[$J]}; 
done;

MOD=${APP}-module;

ABSAPPDIR=${BASEPATH}/${APP};
ABSMODDIR=${BASEPATH}/${MOD};

[ -d "$ABSAPPDIR" ] || echo "$RED Das Verzeichnis ${ABSAPPDIR} existiert nicht; breche ab ...$WHITE";
[ -d "$ABSMODDIR" ] || echo "$RED Das Verzeichnis ${ABSMODDIR} existiert nicht; breche ab ...$WHITE";
[ -d "$ABSAPPDIR" -a -d "$ABSMODDIR" ] || exit 1;

echo -e "$GREEN application path:$WHITE ${ABSAPPDIR}";
echo -e "$GREEN module path:$WHITE ${ABSMODDIR}";

MODDIR=../../${MOD}/module;
THEMEDIR=../../${MOD}/themes;

# Datenbankparameter ermitteln
DBSTRING=$(grep mysql ${ABSAPPDIR}/local/config/vufind/config.ini);
DB=$(echo $DBSTRING | cut -d '/' -f 4);
DBUSER=$(echo $DBSTRING | cut -d '/' -f 3 | cut -d ':' -f 1);
DBPASS=$(echo $DBSTRING | cut -d '/' -f 3 | cut -d ':' -f 2 | cut -d '@' -f 1);
DBHOST=$(echo $DBSTRING | cut -d '/' -f 3 | cut -d ':' -f 2 | cut -d '@' -f 2);

# Quelltexte aus den Repositories holen und initialisieren
echo -n "git-Branch der Module [main]: ";
read MODBRANCH;
[ -z "$MODBRANCH" ] && MODBRANCH="main";

cd $ABSAPPDIR;
echo "VuFind wird aktualisiert ...";
git stash;
git pull;
git stash pop;
php composer.phar update;

cd $ABSMODDIR;
I=0;
for DIR in $(ls module); do
    MODULES[$I]=$DIR;
    (( I++ ));
done;

echo "Die Module werden aktualisiert ...";
git clean -xdf;
git reset --hard;
git pull --rebase;
git checkout $MODBRANCH;
git checkout module;
git checkout themes;

echo -n "vorhandene Module: "
for MODULE in ${MODULES[@]}; do
    echo -ne "$GREEN ${MODULE} ";
done;
echo -e $WHITE;

for DIR in $(ls module); do
    THEME=$(echo $DIR | tr '[:upper:]' '[:lower:]');
    SELECTED="n";
    for MODULE in ${MODULES[@]}; do
        if [ "$DIR" = "$MODULE" ]; then
            SELECTED="y";
            echo -n "Modul ${DIR} deinstallieren? (j/n) [n]: ";
            read YN;
            if [ "$YN" = "j" ]; then
                echo -e "    Modul$RED ${DIR} abgewählt$WHITE";
                rm -rf module/$DIR;
                rm -rf themes/$THEME;
            else
                echo -e "    Modul$GREEN ${DIR} ausgewählt$WHITE";
            fi;
        fi;
    done;
    if [ "$SELECTED" = "n" ]; then
        echo -n "Modul ${DIR} installieren? (j/n) [n]: ";
        read YN;
        if [ "$YN" = "j" ]; then
            echo -e "    Modul$GREEN ${DIR} ausgewählt$WHITE";
        else
            echo -e "    Modul$RED ${DIR} abgewählt$WHITE";
            rm -rf module/$DIR;
            rm -rf themes/$THEME;
        fi;
    fi;
done;

# Abhängigkeiten der Module auflösen

echo "Die Modulabhängigkeiten werden aufgelöst ...";
BEFORES=();
AFTERS=();
for FILE in $(grep -rl 'use \*' module); do
    ACTMOD=$(echo $FILE | cut -d '/' -f2);
    for LINE in "$(grep 'use \*' $FILE)"; do
        DEPS=$(echo "$LINE" | cut -d ';' -f2);
        for MOD in $DEPS; do
            if [ -d module/$MOD ]; then
                MODLISTED=0;
                for J in $(seq 0 $(( ${#BEFORES[*]} - 1 ))); do
                    if [ ${BEFORES[$J]} = "$MOD" ] && [ ${AFTERS[$J]} = "$ACTMOD" ]; then
                        MODLISTED=1;
                    fi;
                done;
                if [ $MODLISTED -eq 0 ]; then
                    BEFORES+=($MOD);
                    AFTERS+=($ACTMOD);
                fi;
            fi;
        done;
    done;
    php linkModules.php $FILE;
done;

# Module in die richtige Reihenfolge bringen

LIST=($(ls module));
N=${#BEFORES[*]};
for J in $(seq 0 $(( $N - 1 ))); do
    for K in $(seq 0 $(( $N - $J - 1 ))); do
        reOrder ${BEFORES[$K]} ${AFTERS[$K]} $LIST;
    done;
done; 

# theme.config.php schreiben
for BASETHEME in belugax bootstrap3plus; do
    cd ${ABSMODDIR}/themes/${BASETHEME};
    echo "${BASETHEME}/theme.config.php schreiben ...";
    touch theme.config.tmp;
    while IFS= read -r LINE; do
        if [ "$LINE" = "MIXINS" ]; then
            for MOD in $(ls ${ABSMODDIR}/module); do
                THEME=$(echo $MOD | tr '[:upper:]' '[:lower:]');
                [ -d "../${THEME}" ] && printf '%s\n' "        '"${THEME}"'," >> theme.config.tmp;
            done;
        else
            printf '%s\n' "$LINE" >> theme.config.tmp;
        fi;
    done < theme.config.php;
    mv theme.config.tmp theme.config.php;
done;

# Symlinks setzen - nur wo sie fehlen

echo "Module werden verlinkt und eingerichtet ...";
cd ${ABSAPPDIR}/themes;
for THEME in $(ls $THEMEDIR); do
    [ -h $THEME ] || ln -s ${THEMEDIR}/$THEME $THEME;
done;

cd ${ABSAPPDIR}/module;
for MOD in $(ls $MODDIR); do
    [ -h $MOD ] || ln -s ${MODDIR}/$MOD $MOD;
done;

# Module einrichten

cd $ABSAPPDIR;
for MOD in $(ls module); do
    if [ -d "module/${MOD}/sql" -a -f "module/${MOD}/sql/mysql.sql" ]; then
        mysql -u $DBUSER -p$DBPASS $DB < module/${MOD}/sql/mysql.sql;
    fi;
    if [ -d "module/${MOD}/composer" -a -f "module/${MOD}/composer/composer.list" ]; then
        while read LINE; do
            php composer.phar require $LINE;
        done < module/${MOD}/composer/composer.list;
    fi;
done;

echo "Die Aktualisierung ist durchgeführt";

exit $?;
