
CREATE TABLE `project_visits` (
  `VisitId` int(11) NOT NULL,
  `Entity` varchar(50) NOT NULL,
  `EntityId` int(11) DEFAULT NULL COMMENT 'If null, it refers to some entity index',
  `UserId` int(11) NOT NULL,
  `IpAddress` varchar(255) DEFAULT NULL,
  `UserAgent` varchar(255) DEFAULT NULL,
  `VisitedAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `project_visits`
  ADD PRIMARY KEY (`VisitId`);

ALTER TABLE `project_visits`
  MODIFY `VisitId` int(11) NOT NULL AUTO_INCREMENT;
