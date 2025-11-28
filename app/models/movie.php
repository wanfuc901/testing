<?php
class Movie {
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }

    public function search($keyword) {
        $keyword = "%{$keyword}%";
        $sql = $this->conn->prepare("
            SELECT movie_id, title, poster_url, duration, genre, release_date
            FROM movies 
            WHERE title LIKE ? OR genre LIKE ?
            ORDER BY release_date DESC
        ");
        $sql->bind_param("ss", $keyword, $keyword);
        $sql->execute();
        return $sql->get_result();
    }
}
?>
