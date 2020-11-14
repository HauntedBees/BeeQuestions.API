# BeeQuestions.API

## wut
The backend for Bee Questions! - the world's first A&Q website. Check out the [frontend](https://github.com/HauntedBees/BeeQuestions.Site/), too!

## dependencies
This is a [BeeAPI](https://github.com/HauntedBees/BeeAPI) Module, so you should place the contents of this repository in a "Questions" folder at the root of a BeeAPI directory. If you already have BeeAPI, you can also run `git submodule add https://github.com/HauntedBees/BeeQuestions.API Questions` there.

## license
Bee Questions is licensed with the [GNU Affero General Public License](https://www.gnu.org/licenses/agpl-3.0.en.html).

## want to make changes?
It's not done yet gimme a minute.

## setup
See the [BeeAPI Setup](https://github.com/HauntedBees/BeeAPI#setup) to set up BeeAPI. See the steps above in the [dependencies](#dependencies) section for adding the Bee Questions! Module to it. Additionally, there should be a `badwords.txt` file present in the `Questions` directory, containing one regular expression per line (without the /'s around it) that - if present in user input - will block the input from being pushed to the database. This file is not provided in the repository because I don't want a text file full of slurs here.