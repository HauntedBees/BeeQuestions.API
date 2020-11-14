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
require_once "autoload.php";
//use Abraham\TwitterOAuth\TwitterOAuth;

class QuestionsController extends BeeController {
    public function __construct() { parent::__construct("bqdb"); }
    /* #region Auth */
    public function PostRegistration(BeeCredentials $credentials) {
        try {
            $auth = new BeeAuth();
            $userInfo = $auth->Register($credentials);
            $bqdbUser = $this->CreateBQUser($userInfo->id);
            $token = $this->GenerateUserToken($auth, $userInfo);
            return $this->response->Custom([
                "token" => $token,
                "user" => $bqdbUser,
                "isNew" => true
            ]);
        } catch(BeeAuthException $e) {
            return $this->response->Error($e->getMessage());
        }
    }
    public function GetOAuth(string $source) {
        $root = $this->GetConfigInfo("auth", "basepath");
        $responseURL = "$root/bq/oauth?from=twitter";
        $ap = new OauthHandler();
        $ap->MakeRequest($source, $responseURL);
    }
    public function PostOAuthCallback(BeeOAuthResponse $response) {
        $oauth = new OauthHandler();
        $accountInfo = $oauth->HandleResponse($response);
        $auth = new BeeAuth();
        $userInfo = $auth->RegisterLoginOAuth($accountInfo);
        return $this->GetOrCreateBQUserAndReturnResponse($auth, $userInfo);
    }
    public function PostBeeLogin(BeeCredentials $credentials) {
        try {
            $auth = new BeeAuth();
            $userInfo = $auth->Login($credentials);
            return $this->GetOrCreateBQUserAndReturnResponse($auth, $userInfo);
        } catch(BeeAuthException $e) {
            return $this->response->Unauthorized("Invalid email address or password.");
        }
    }
    private function GetOrCreateBQUserAndReturnResponse(BeeAuth $auth, BeeUserToken $userInfo) {
        $bqdbUser = $this->db->GetObject("BQUser",
            "SELECT u.beeauthid, u.displayname, u.joined, u.score, u.level, l.questionsPerDay, l.answersPerDay,
                COUNT(DISTINCT q.id) AS questionsAsked, COUNT(DISTINCT a.id) AS answersGiven, CASE
                    WHEN u.blockeduntil IS NULL THEN NULL
                    WHEN u.blockeduntil < NOW() THEN NULL
                    ELSE u.blockeduntil
                END AS blockdate
            FROM users u
                INNER JOIN userlevel l ON u.level = l.level
                LEFT JOIN question q ON q.user = u.id AND DAY(q.posted) = DAY(NOW())
                LEFT JOIN answer a ON a.user = u.id AND DAY(a.opened) = DAY(NOW())
            WHERE u.beeauthid = :i
            GROUP BY u.beeauthid, u.displayname, u.joined, u.score, u.level, l.questionsperday, l.answersperday", ["i" => $userInfo->id]);
        $isNew = false;
        if($bqdbUser == null) { // BeeAuth User Only
            $isNew = true;
            $bqdbUser = $this->CreateBQUser($userInfo->id);
        } else {
            $this->db->ExecuteNonQuery("UPDATE users SET lastlogin = NOW() WHERE id = :id", ["id" => $userInfo->id]);
        }
        $authdb = new BeeDB("auth");
        $sourceInfo = $authdb->GetDataRow(
            "SELECT IFNULL(source, 'email') AS source,
                CASE
                    WHEN source = 'twitter' THEN externalname
                    ELSE username
                END AS name
            FROM users
            WHERE id = :i", ["i" => $bqdbUser->beeauthid]);
        $bqdbUser->source = $sourceInfo["source"];
        $bqdbUser->sourcename = $sourceInfo["name"];
        $bqdbUser->lastlogin = date(DATE_ATOM);
        unset($bqdbUser->beeauthid);
        $token = $this->GenerateUserToken($auth, $userInfo);
        return $this->response->Custom([
            "token" => $token,
            "user" => $bqdbUser,
            "isNew" => $isNew
        ]);
    }
    private function CreateBQUser(int $beeAuthID):BQUser {
        $nameParts1 = ["Football", "Computer", "Space", "Granola", "Hockey", "Baseball", "Taco", "Banana", "Pasta", "Soup", "Bread", "Ska", "Bee", "Punk"];
        $nameParts2 = ["Captain", "Nerd", "Machine", "Snack", "Ball", "Bat", "Bell", "Tree", "Plate", "Bowl", "Tuesdays", "Leader", "Bee", "Punk"];
        $displayname = $nameParts1[array_rand($nameParts1)].$nameParts2[array_rand($nameParts2)].random_int(100000, 999999);
        $username = "beeauthuser$beeAuthID";
        $this->db->ExecuteNonQuery("INSERT INTO users (beeauthid, name, displayname, joined, lastlogin, score, level) VALUES (:si, :un, :dn, NOW(), NOW(), 100, 2)", [
            "si" => $beeAuthID,
            "dn" => $displayname,
            "un" => $username
        ]);
        $u = new BQUser();
        $u->displayname = $displayname;
        $u->joined = date(DATE_ATOM);
        $u->beeauthid = $beeAuthID;
        return $u;
    }
    private function GenerateUserToken(BeeAuth $auth, BeeUserToken $but):string {
        $but->id = $this->db->GetInt("SELECT id FROM users WHERE beeauthid = :id", ["id" => $but->id]);
        return $auth->GenerateJWTToken($but);
    }
    /* #endregion */
    /* #region Answers */
    /** @return BQAnswer */
     public function GetAnswer(string $answerURL) {
        $userID = $this->GetMaybeUserId();
        $answer = $this->db->GetObject("BQFullAnswer", 
            "SELECT a.id, u.displayname AS author, a.answer, a.status, a.opened, a.closed, a.bestquestion
            FROM answer a
                INNER JOIN users u ON a.user = u.id
            WHERE a.url = :id", ["id" => $answerURL]);
        if($answer === null) { throw new Exception("Answer not found."); }
        $answer->tags = $this->db->GetStrings("SELECT t.name FROM tag t INNER JOIN answer_tag atx ON t.id = atx.tag WHERE atx.answer = :id", ["id" => $answer->id]);
        if($userID > 0) {
            $answer->liked = $this->db->GetBool("SELECT COUNT(*) FROM answer_user_likes WHERE answer = :a AND user = :u", ["a" => $answer->id, "u" => $userID]);
        }
        $answer->questions = $this->GetQuestions($answer->id, $userID);
        unset($answer->id);
        return $this->response->OK($answer);
    }
    /** @return bool */
    public function PostAnswerLikeToggle(string $answerURL) {
        $answerID = $this->FindAnswerID($answerURL);
        if($answerID === 0) { return $this->response->Error("Invalid answer."); }
        try {
            $this->db->BeginTransaction();
            $tokenID = $this->GetMaybeUserId();
            if($tokenID === 0) { return $this->response->Unauthorized("Please log in to bookmark posts."); }
            $hasAnswerLike = $this->db->GetBool("SELECT COUNT(*) FROM answer_user_likes WHERE answer = :a AND user = :u", ["a" => $answerID, "u" => $tokenID]);
            if($hasAnswerLike) {
                $this->db->ExecuteNonQuery("DELETE FROM answer_user_likes WHERE answer = :a AND user = :u", ["a" => $answerID, "u" => $tokenID]);
                $this->db->ExecuteNonQuery("UPDATE answer SET score = score - 1 WHERE id = :a", ["a" => $answerID]);
            } else {
                $this->db->ExecuteNonQuery("INSERT INTO answer_user_likes (answer, user) VALUES (:a, :u)", ["a" => $answerID, "u" => $tokenID]);
                $this->db->ExecuteNonQuery("UPDATE answer SET score = score + 1 WHERE id = :a", ["a" => $answerID]);
            }
            $this->db->CommitTransaction();
            return $this->response->OK(!$hasAnswerLike);
        } catch(Throwable $e) {
            $this->db->RollbackTransaction();
            throw $e;
        }
    }
    /** @return BQAnswer[] */
    public function GetUserAnswers(string $displayName, ?int $offset = 0, ?int $pageSize = 10) {
        $userID = $this->GetUserIdFromDisplayName($displayName);
        if($userID === 0) { return $this->response->Error("Invalid user"); }
        $page = $offset * $pageSize;
        $tbl = $this->db->GetObjects("BQAnswer", 
            "SELECT a.url, u.displayname AS author, a.answer, a.status, a.opened, a.closed, COUNT(DISTINCT q.id) AS questions, IFNULL(GROUP_CONCAT(DISTINCT t.name SEPARATOR '|'), '') AS tagsStr
            FROM answer a
                INNER JOIN users u ON a.user = u.id
                LEFT JOIN question q ON a.id = q.answer
                LEFT JOIN answer_tag atx ON a.id = atx.answer
                LEFT JOIN tag t ON atx.tag = t.id
            WHERE a.user = :u
            GROUP BY a.url, u.displayname, a.answer, a.status, a.opened, a.closed
            ORDER BY a.opened DESC
            LIMIT $page, $pageSize", ["u" => $userID]);
        return $this->response->OK($tbl);
    }
    /** @return BQAnswer[] */
    public function GetUserBookmarkedAnswers(?int $offset = 0, ?int $pageSize = 10) {
        $userID = $this->GetMaybeUserId();
        if($userID === 0) { return $this->response->Error("Invalid user"); }
        $page = $offset * $pageSize;
        $tbl = $this->db->GetObjects("BQAnswer", 
            "SELECT a.url, u.displayname AS author, a.answer, a.status, a.opened, a.closed, COUNT(DISTINCT q.id) AS questions, IFNULL(GROUP_CONCAT(DISTINCT t.name SEPARATOR '|'), '') AS tagsStr
            FROM answer a
                INNER JOIN users u ON a.user = u.id
                INNER JOIN answer_user_likes aux ON a.id = aux.answer
                LEFT JOIN question q ON a.id = q.answer
                LEFT JOIN answer_tag atx ON a.id = atx.answer
                LEFT JOIN tag t ON atx.tag = t.id
            WHERE aux.user = :u
            GROUP BY a.url, u.displayname, a.answer, a.status, a.opened, a.closed
            ORDER BY a.opened DESC
            LIMIT $page, $pageSize", ["u" => $userID]);
        return $this->response->OK($tbl);
    }
    /** @return BQAnswer[] */
    public function GetHomePageAnswers(int $type, ?int $offset = 0, ?int $pageSize = 10) {
        [$whereQuery, $orderBy] = $this->GetWhereAndOrderQueries($type);
        $page = $offset * $pageSize;
        $tbl = $this->db->GetObjects("BQAnswer", 
            "SELECT a.url, u.displayname AS author, a.answer, a.status, a.opened, a.closed, COUNT(DISTINCT q.id) AS questions, IFNULL(GROUP_CONCAT(DISTINCT t.name SEPARATOR '|'), '') AS tagsStr
            FROM answer a
                INNER JOIN users u ON a.user = u.id
                LEFT JOIN question q ON a.id = q.answer
                LEFT JOIN answer_tag atx ON a.id = atx.answer
                LEFT JOIN tag t ON atx.tag = t.id
            $whereQuery
            GROUP BY a.url, u.displayname, a.answer, a.status, a.opened, a.closed
            $orderBy
            LIMIT $page, $pageSize");
        return $this->response->OK($tbl);
    }
    /** @return BQAnswer[] */
    public function GetTagAnswers(string $tag, int $type, ?int $offset = 0, ?int $pageSize = 10) {
        [$whereQuery, $orderBy] = $this->GetWhereAndOrderQueries($type);
        $page = $offset * $pageSize;
        $tbl = $this->db->GetObjects("BQAnswer", 
            "SELECT a.url, u.displayname AS author, a.answer, a.status, a.opened, a.closed, COUNT(DISTINCT q.id) AS questions, CONCAT('|', GROUP_CONCAT(DISTINCT t.name SEPARATOR '|') , '|') AS tagsStr
            FROM answer a
                INNER JOIN users u ON a.user = u.id
                LEFT JOIN question q ON a.id = q.answer
                LEFT JOIN answer_tag atx ON a.id = atx.answer
                LEFT JOIN tag t ON atx.tag = t.id
            $whereQuery
            GROUP BY a.url, u.displayname, a.answer, a.status, a.opened, a.closed
            HAVING tagsStr LIKE :t
            $orderBy
            LIMIT $page, $pageSize", ["t" => "%|$tag|%"]);
        return $this->response->OK($tbl);
    }
    private function GetWhereAndOrderQueries(int $type):array {
        $whereQuery = "";
        $orderBy = "";
        switch($type) {
            case 0: // popular
                $whereQuery = "WHERE a.status = 0";
                $orderBy = "ORDER BY a.score DESC"; // TODO: score/views/questions?
                break;
            case 1: // recent
                $whereQuery = "WHERE a.status = 0";
                $orderBy = "ORDER BY a.opened DESC";
                break;
            case 2: // needs love
                $whereQuery = "WHERE status = 0";
                $orderBy = "ORDER BY a.views ASC"; // TODO: score/views/questions?
                break;
            case 3: // in voting
                $whereQuery = "WHERE a.status = 1";
                break;
            case 4: // closed
                $whereQuery = "WHERE a.status = 2";
                break;
        }
        return [$whereQuery, $orderBy];
    }
    private function CreateID(int $id, string $prefix=""):string {
        $suffix = random_int(1000, 9999999);
        return base64_encode("$prefix$id-$suffix");
    }
    private function FindAnswerID(string $url):int {
        return $this->db->GetInt("SELECT id FROM answer WHERE url = :u", ["u" => $url]);
    }
    /* #endregion */
    /* #region Questions */
    /** @return BQQuestion[] */
    public function PostQuestion(BQPostedQuestion $question) {
        $tokenID = $this->GetMaybeUserId();
        if($tokenID === 0) { return $this->response->Unauthorized("Please log in to ask questions."); }

        $userID = $this->db->GetInt(
            "SELECT u.id
            FROM users u
                INNER JOIN userlevel l ON u.level = l.level
                LEFT JOIN question q ON q.user = u.id AND DAY(q.posted) = DAY(NOW())
            WHERE u.id = :i
                AND (u.blockeduntil IS NULL OR NOW() > u.blockeduntil)
            GROUP BY u.id, l.questionsperday
            HAVING COUNT(q.id) < l.questionsperday", ["i" => $tokenID]);
        if(empty($userID)) { return $this->response->Error("You can't ask any more questions today. Try again tomorrow!"); }
        
        if(empty($question->question)) { return $this->response->Error("Please enter a question."); }
        if(strlen($question->question) > 500) { return $this->response->Error("Questions can not exceed 500 characters."); }
        if(empty($question->answer)) { $this->response->Error("Invalid answer."); }
        $answerID = $this->db->GetInt("SELECT id FROM answer WHERE url = :i AND status = 0", ["i" => $question->answer]);
        if(empty($answerID)) { $this->response->Error("Invalid answer."); }

        $question->question = $this->ValidateText($question->question);
        $this->db->InsertAndReturnID("INSERT INTO question (answer, user, question, posted, score) VALUES (:a, :u, :q, NOW(), 0)", [
            "a" => $answerID,
            "u" => $userID,
            "q" => $question->question
        ]);
        return $this->response->OK($this->GetQuestions($answerID, $userID));
    }
    /** @return bool */
    public function PostQuestionLikeToggle(int $questionID) {
        try {
            $this->db->BeginTransaction();
            $tokenID = $this->GetMaybeUserId();
            if($tokenID === 0) { return $this->response->Unauthorized("Please log in to like questions."); }
            $hasQuestionLike = $this->db->GetBool("SELECT COUNT(*) FROM question_user_likes WHERE question = :q AND user = :u", ["q" => $questionID, "u" => $tokenID]);
            if($hasQuestionLike) {
                $this->db->ExecuteNonQuery("DELETE FROM question_user_likes WHERE question = :q AND user = :u", ["q" => $questionID, "u" => $tokenID]);
                $this->db->ExecuteNonQuery("UPDATE question SET score = score - 1 WHERE id = :q", ["q" => $questionID]);
            } else {
                $this->db->ExecuteNonQuery("INSERT INTO question_user_likes (question, user) VALUES (:q, :u)", ["q" => $questionID, "u" => $tokenID]);
                $this->db->ExecuteNonQuery("UPDATE question SET score = score + 1 WHERE id = :q", ["q" => $questionID]);
            }
            $this->db->CommitTransaction();
            return $this->response->OK(!$hasQuestionLike);
        } catch(Throwable $e) {
            $this->db->RollbackTransaction();
            throw $e;
        }
    }
    /** @return BQQuestion[] */
    public function GetUserQuestions(string $displayName, ?int $offset = 0, ?int $pageSize = 10) {
        $userID = $this->GetUserIdFromDisplayName($displayName);
        if($userID === 0) { return $this->response->Error("Invalid user"); }
        $page = $offset * $pageSize;
        $tbl = $this->db->GetObjects("BQQuestion", 
            "SELECT q.id, u.displayname AS author, q.question, q.posted, q.score, COUNT(x.user) AS liked,
                (q.user = :u) AS yours, a.answer, (q.id = a.bestquestion) AS winner, a.url AS answerURL
            FROM question q
                INNER JOIN users u ON q.user = u.id
                INNER JOIN answer a ON q.answer = a.id
                LEFT JOIN question_user_likes x ON x.question = q.id AND x.user = :u
            WHERE q.user = :u
            GROUP BY q.id
            LIMIT $page, $pageSize", ["u" => $userID]);
        return $this->response->OK($tbl);
    }
    /** @return BQQuestion[] */
    public function GetUserBookmarkedQuestions(?int $offset = 0, ?int $pageSize = 10) {
        $userID = $this->GetMaybeUserId();
        if($userID === 0) { return $this->response->Error("Invalid user"); }
        $page = $offset * $pageSize;
        $tbl = $this->db->GetObjects("BQQuestion", 
            "SELECT q.id, u.displayname AS author, q.question, q.posted, q.score, COUNT(x.user) AS liked,
                (q.user = :u) AS yours, a.answer, (q.id = a.bestquestion) AS winner, a.url AS answerURL
            FROM question q
                INNER JOIN users u ON q.user = u.id
                INNER JOIN question_user_likes qux ON q.id = qux.question
                INNER JOIN answer a ON q.answer = a.id
                LEFT JOIN question_user_likes x ON x.question = q.id AND x.user = :u
            WHERE qux.user = :u
            GROUP BY q.id
            LIMIT $page, $pageSize", ["u" => $userID]);
        return $this->response->OK($tbl);
    }
    private function GetQuestions(int $answerID, int $userID):array {
        return $this->db->GetObjects("BQQuestion", 
            "SELECT q.id, u.displayname AS author, q.question, q.posted, q.score, COUNT(x.user) AS liked, (q.user = $userID) AS yours
            FROM question q
                INNER JOIN users u ON q.user = u.id
                LEFT JOIN question_user_likes x ON x.question = q.id AND x.user = $userID
            WHERE q.answer = :id
            GROUP BY q.id", ["id" => $answerID]);
    }
    /* #endregion */
    /* #region Tags */
    /** @return string[] */
    public function GetTagPrefixes() {
        return $this->response->OK($this->db->GetStrings("SELECT DISTINCT LEFT(UPPER(name), 1) FROM tag ORDER BY name ASC"));
    }
    /** @return BQTag[] */
    public function GetTagBrowse(string $prefix, ?int $offset = 0, ?int $pageSize = 10) {
        $page = $offset * $pageSize;
        $whereQuery = ""; $whereParams = [];
        if($prefix !== "all") {
            $whereQuery = "WHERE name LIKE :t";
            $whereParams = ["t" => "$prefix%"];
        }
        return $this->response->OK($this->db->GetObjects("BQTag",
            "SELECT t.name, COUNT(at.answer) AS answers
             FROM tag t
                 INNER JOIN answer_tag at ON t.id = at.tag
             $whereQuery
             GROUP BY t.name
             ORDER BY t.name ASC
             LIMIT $page, $pageSize", $whereParams));
    }
    /** @return string[] */
    public function GetTags(string $type, ?int $offset = 0, ?int $pageSize = 10) {
        $page = $offset * $pageSize;
        $whereQuery = ""; $whereParams = []; $orderBy = "";
        if($type === "popular") {
            $orderBy = "ORDER BY COUNT(at.answer) DESC";
        } else {
            $whereQuery = "WHERE t.name LIKE :t";
            $whereParams["t"] = "$type%";
            $orderBy = "ORDER BY t.name ASC";
        }
        $res = $this->db->GetStrings(
            "SELECT t.name
             FROM tag t
                 INNER JOIN answer_tag at ON t.id = at.tag
                 INNER JOIN answer a ON at.answer = a.id AND a.changed > DATE_SUB(NOW(), INTERVAL 1 MONTH)
             $whereQuery
             GROUP BY t.name
             $orderBy
             LIMIT $page, $pageSize", $whereParams);
        if(count($res) === 0 && $type === "popular") { // no recently popular tags exist, just pick the most popular overall
            $res = $this->db->GetStrings(
                "SELECT t.name
                 FROM tag t
                     LEFT JOIN answer_tag at ON t.id = at.tag
                 GROUP BY t.name
                 ORDER BY COUNT(at.answer) DESC
                 LIMIT $page, $pageSize");
        }
        return $this->response->OK($res);
    }
    /* #endregion */
    /* #region User */
    /** @return bool */
    public function PostDisplayName(string $newName) {
        $userID = $this->GetMaybeUserId();
        if($userID === 0) { return $this->response->Unauthorized("Please log in to change your display name."); }
        $newName = $this->ValidateText($newName);
        $alreadyExists = $this->db->GetBool("SELECT COUNT(*) FROM users WHERE displayname = :u", ["u" => $newName]);
        if($alreadyExists) {
            return $this->response->Error("Another user already has this display name. :(");
        }
        $this->db->ExecuteNonQuery("UPDATE users SET displayname = :u WHERE id = :i", ["u" => $newName, "i" => $userID]);
        return $this->response->OK(true);
    }
    public function GetAdditionalUserInfo() {
        $userID = $this->GetMaybeUserId();
        if($userID === 0) { return $this->response->Unauthorized("Please log in to access this functionality."); }
        $totalCounts = $this->db->GetDataRow(
            "SELECT u.displayname, COUNT(DISTINCT a.id) AS answers, COUNT(DISTINCT q.id) AS questions, COUNT(DISTINCT aux.answer) AS answerLikes, 
                COUNT(DISTINCT qux.question) AS questionLikes, COUNT(DISTINCT ba.id) AS bestQuestions
            FROM users u
                LEFT JOIN answer a ON a.user = u.id
                LEFT JOIN question q ON q.user = u.id
                LEFT JOIN answer ba ON q.id = ba.bestquestion
                LEFT JOIN answer_user_likes aux ON aux.user = u.id
                LEFT JOIN question_user_likes qux ON qux.user = u.id
            WHERE u.id = :i
            GROUP BY u.id", ["i" => $userID]);
        $this->response->OK($totalCounts);
    }
    /* #endregion */
    /* #region Helpers */
    private function GetUserIdFromDisplayName(string $displayName):int {
        return $this->db->GetInt("SELECT id FROM users WHERE displayname = :d", ["d" => $displayName]);
    }
    private function GetMaybeUserId():int {
        $auth = new BeeAuth();
        try {
            $token = $auth->GetToken("BeeUserToken");
            return $token->id;
        } catch(Exception $ex) {
            return 0;
        }
    }
    private function ValidateText(string $origStr):string { // TODO: ensure the ISO-8859-1//TRANSLIT works on live site
        $str = strtolower(iconv("UTF-8", "ISO-8859-1//TRANSLIT", $origStr));
        $str = preg_replace("/-/", "", $str);
        $str = preg_replace("/[^a-z0-9]/", " ", $str);
        $str = preg_replace("/\s+/", " ", $str);
        $replacements = [
            "a" => "@|4|À|Á|Â|Ã|Ä|Å|à|á|â|ã|ä|å|ª",
            "b" => "8|ß", 
            "c" => "\(|\[|Ç|ç|¢|©",
            "d" => "ð|Ð",
            "e" => "3|³|È|É|Ê|Ë|è|é|ê|ë", 
            "f" => "ph",
            "g" => "6",
            "i" => "Ì|Í|Î|Ï|ì|í|î|ï|¹|¡",
            "l" => "£", 
            "n" => "Ñ|ñ", 
            "o" => "0|º|Ò|Ó|Ô|Õ|Ö|Ø|ò|ó|ô|õ|ö|ø|¤|°", 
            "p" => "¶", 
            "q" => "9", 
            "r" => "®",
            "s" => "§", 
            "t" => "7|±", 
            "u" => "µ|Ù|Ú|Û|Ü|ù|ú|û|ü", 
            "w" => "vv", 
            "x" => "×", 
            "y" => "Ý|Ÿ|ý|ÿ",
            "z" => "2|²"
        ];
        foreach($replacements as $letter => $alternates) { $str = preg_replace("/.*$alternates.*/", $letter, $str); }

        $maybeReplacements = [
            "" => "",
            "a" => "Æ|æ",
            "b" => "þ|Þ",
            "c" => "k",
            "e" => "Æ|æ",
            "i" => "l|!|1",
            "k" => "c",
            "l" => "i|!|1",
            "p" => "þ|Þ",
            "u" => "v",
            "v" => "u"
        ];
        $badWords = preg_split("/\r?\n/", file_get_contents(dirname(__FILE__)."/badwords.txt"));
        foreach($maybeReplacements as $letter => $alternates) {
            $test = $letter === "" ? $str : preg_replace("/$alternates/", $letter, $str);
            foreach($badWords as $badWord) { if(preg_match("/$badWord/", $test)) { throw new BeeException("Don't say words like that, come on."); } }
        }

        $origStr = preg_replace("/[\x01-\x1F\x80-\xFF]/", "", $origStr);
        return $origStr;
    }
    /* #endregion */
}
?>