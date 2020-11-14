<?php declare(strict_types=1);
/**
 * Bee Questions
 * @copyright 2020 Haunted Bees Productions
 * @author Sean Finch <fench@hauntedbees.com>
 * @license https://www.gnu.org/licenses/agpl-3.0.en.html GNU Affero General Public License
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * @see https://github.com/HauntedBees/BeeAPI
 */
/* #region Auth */
class BQBlockUser {
    public int $userID;
    public string $blocktype;
}
class BQUserToken extends BeeUserToken {
    public BQUser $bquser;
}
class BQUser {
    public int $beeauthid;
    public string $displayname;
    public string $joined;
    public string $lastlogin;
    public int $score = 100;
    public int $level = 2;
    public int $questionsPerDay = 9;
    public int $answersPerDay = 3;
    public int $questionsAsked = 0;
    public int $answersGiven = 0;
    public ?string $blockdate;
    public string $source;
    public string $sourcename;
}
/* #endregion */
/* #region Answers */
class BQAnswer {
    public int $id;
    public string $url;
    public string $author;
    public string $answer;
    public int $status;
    public int $questions;
    public string $opened;
    public ?string $closed;
    public ?int $bestquestion;
    public array $tags;
    private string $tagsStr;
    public function __construct() {
        if(empty($this->tagsStr)) {
            $this->tags = [];
        } else {
            $this->tags = explode("|", trim($this->tagsStr, "|"));
        }
    }
}
class BQFullAnswer {
    public int $id;
    public string $author;
    public string $answer;
    public int $status;
    public array $questions;
    public string $opened;
    public ?string $closed;
    public ?int $bestquestion;
    public array $tags;
    public bool $liked = false;
}
class BQQuestion {
    public int $id;
    public string $author;
    public string $question;
    public string $posted;
    public int $score;
    public bool $liked;
    public bool $yours;
    public ?bool $winner;
    public ?string $answer;
    public ?string $answerURL;
}
class BQTag {
    public string $tag;
    public int $answers;
}
class BQPostedQuestion {
    public string $answer;
    public string $question;
}
/* #endregion */
?>