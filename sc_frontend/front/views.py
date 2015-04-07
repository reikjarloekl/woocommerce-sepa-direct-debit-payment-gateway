from django.contrib.auth.decorators import login_required
from django.http import HttpResponse
from django.views.decorators.clickjacking import xframe_options_exempt
from front.models import Camera

@xframe_options_exempt
@login_required
def print_cookies(request):
    print 'Printing cookies.'
    print 'User is {}.'.format(request.user.username if request.user.is_authenticated() else 'None')
    cameras = Camera.objects.get(user=request.user)
    print 'Cameras for user: {}'.format(cameras)
    return HttpResponse(str(request.COOKIES) + '<br/>' + str(cameras))
