<?php $approvals = \App\Modules\Messaging\Models\SenderIDMnoApproval::all(); foreach($approvals as $a) { $a->status = 'APPROVED'; $a->save(); } echo 'Approved all MNO Approvals!';
