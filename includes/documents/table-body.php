<tbody>
    <?php while ($row = $stmt->fetch()): ?>
        <tr data-id="<?php echo $row['id']; ?>">
            <td><?php echo $row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['title']); ?></td>
            <td><?php echo htmlspecialchars($row['sender_name']); ?></td>
            <td><?php echo htmlspecialchars($row['receiver_name']); ?></td>
            <td><span class='badge <?php echo getStatusClass($row['status']); ?>'><?php echo getStatusLabel($row['status']); ?></span></td>
            <td><?php echo formatDate($row['created_at']); ?></td>
            <td><?php echo formatDate($row['updated_at']); ?></td>
            <td class="actions-cell">
                <i class="fas fa-ellipsis-v action-menu-icon"></i>
            </td>
        </tr>
    <?php endwhile; ?>
</tbody> 