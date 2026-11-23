<?php
declare(strict_types=1);
namespace Demo\Core;
use PDO;
final class Paginator {
    /** SQL fragments must be defined by the model, never supplied by the client. */
    public static function run(PDO $db,string $select,string $from,string $order,array $params,array $query): array {
        $page=max(1,(int)(is_scalar($query['page']??null)?$query['page']:1));
        $size=max(1,min(100,(int)(is_scalar($query['per_page']??null)?$query['per_page']:15)));
        $count=$db->prepare('SELECT COUNT(*) '.$from);$count->execute($params);
        $total=(int)$count->fetchColumn();$last=max(1,(int)ceil($total/$size));$page=min($page,$last);
        $offset=($page-1)*$size;
        $stmt=$db->prepare($select.' '.$from.' ORDER BY '.$order.' LIMIT '.$size.' OFFSET '.$offset);
        $stmt->execute($params);
        return ['data'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'pagination'=>['current_page'=>$page,'per_page'=>$size,'total'=>$total,'last_page'=>$last]];
    }
    public static function search(array $query): string {
        return is_string($query['search']??null)?mb_substr(trim($query['search']),0,160):'';
    }
}
